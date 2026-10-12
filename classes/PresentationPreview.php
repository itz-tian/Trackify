<?php

class PresentationPreview {
    private const MAX_SLIDES = 100;
    private const MAX_RENDER_SECONDS = 120;
    private const RENDER_DPI = 96;
    private const MAX_IMAGE_DIMENSION = 1600;

    private string $cacheRoot;

    public function __construct() {
        $this->cacheRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'trackify-presentation-previews';
    }

    public function create(string $sourcePath, int $documentId): array {
        set_time_limit(self::MAX_RENDER_SECONDS + 15);
        $hash = hash_file('sha256', $sourcePath);
        if ($hash === false) {
            throw new RuntimeException('The presentation could not be read.');
        }

        $cacheDir = $this->cacheRoot . DIRECTORY_SEPARATOR . $hash;
        $lockPath = $this->cacheRoot . DIRECTORY_SEPARATOR . $hash . '.lock';
        $this->ensureDirectory($this->cacheRoot);

        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('The presentation preview cache is unavailable.');
        }

        try {
            $this->acquireLock($lock);

            $slideCount = $this->cachedSlideCount($cacheDir);
            if ($slideCount === null) {
                set_time_limit(self::MAX_RENDER_SECONDS + 10);
                $slideCount = $this->render($sourcePath, $cacheDir);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $slides = [];
        for ($slide = 1; $slide <= $slideCount; $slide++) {
            $slides[] = APP_URL . '/presentation_preview.php?' . http_build_query([
                'id' => $documentId,
                'slide' => $slide,
                'key' => $hash,
            ]);
        }

        return ['count' => $slideCount, 'key' => $hash, 'slides' => $slides];
    }

    public function getCachedSlide(int $slide, string $hash): string {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $hash) || $slide < 1) {
            throw new InvalidArgumentException('Invalid slide request.');
        }

        $cacheDir = $this->cacheRoot . DIRECTORY_SEPARATOR . $hash;
        $path = $cacheDir . DIRECTORY_SEPARATOR . sprintf('slide-%03d.png', $slide);
        if (is_file($path) && filesize($path) > 0) {
            return $path;
        }

        set_time_limit(self::MAX_RENDER_SECONDS + 15);
        $lock = fopen($this->cacheRoot . DIRECTORY_SEPARATOR . $hash . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('The presentation preview cache is unavailable.');
        }

        try {
            $this->acquireLock($lock);

            $count = $this->cachedSlideCount($cacheDir);
            if ($count === null) {
                throw new RuntimeException('The presentation preview has expired. Reload the document and try again.');
            }
            if ($slide > $count) {
                throw new InvalidArgumentException('Slide number is outside this presentation.');
            }
            if (!is_file($path) || filesize($path) === 0) {
                set_time_limit(self::MAX_RENDER_SECONDS + 10);
                $this->renderSlide($cacheDir, $slide);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $path;
    }

    private function acquireLock($lock): void {
        $deadline = microtime(true) + self::MAX_RENDER_SECONDS + 5;
        do {
            if (flock($lock, LOCK_EX | LOCK_NB)) {
                return;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('The presentation preview is busy. Please reload the document and try again.');
    }

    private function render(string $sourcePath, string $cacheDir): int {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['ppt', 'pptx'], true)) {
            throw new InvalidArgumentException('Only PPT and PPTX presentations can be rendered.');
        }

        $soffice = $this->findExecutable('soffice');
        $pdfinfo = $this->findExecutable('pdfinfo');
        $pdftoppm = $this->findExecutable('pdftoppm');
        $deadline = microtime(true) + self::MAX_RENDER_SECONDS;
        $workingDir = $this->cacheRoot . DIRECTORY_SEPARATOR . 'work-' . bin2hex(random_bytes(12));
        $this->ensureDirectory($workingDir);

        try {
            $inputPath = $workingDir . DIRECTORY_SEPARATOR . 'presentation.' . $extension;
            if (!copy($sourcePath, $inputPath)) {
                throw new RuntimeException('The presentation could not be staged for rendering.');
            }

            $officeProfile = 'file://' . $workingDir . DIRECTORY_SEPARATOR . 'office-profile';
            $this->runCommand([
                $soffice,
                '-env:UserInstallation=' . $officeProfile,
                '--headless',
                '--convert-to',
                'pdf:impress_pdf_Export',
                '--outdir',
                $workingDir,
                $inputPath,
            ], $deadline);

            $pdfPath = $workingDir . DIRECTORY_SEPARATOR . 'presentation.pdf';
            if (!is_file($pdfPath)) {
                throw new RuntimeException('The presentation could not be converted to PDF.');
            }

            $pdfInfo = $this->runCommand([$pdfinfo, $pdfPath], $deadline);
            if (!preg_match('/^Pages:\s+(\d+)\s*$/m', $pdfInfo, $matches)) {
                throw new RuntimeException('The converted presentation has no readable pages.');
            }

            $slideCount = (int)$matches[1];
            if ($slideCount < 1 || $slideCount > self::MAX_SLIDES) {
                throw new RuntimeException('Presentations must contain between 1 and ' . self::MAX_SLIDES . ' slides to preview.');
            }

            $this->renderSlide($workingDir, 1, $deadline);

            unlink($inputPath);
            $profileDirectory = $workingDir . DIRECTORY_SEPARATOR . 'office-profile';
            if (is_dir($profileDirectory)) {
                $this->removeDirectory($profileDirectory);
            }

            $manifest = $workingDir . DIRECTORY_SEPARATOR . 'slide-count';
            if (file_put_contents($manifest, (string)$slideCount, LOCK_EX) === false) {
                throw new RuntimeException('The rendered slide list could not be saved.');
            }

            if (is_dir($cacheDir)) {
                $this->removeDirectory($cacheDir);
            }
            if (!rename($workingDir, $cacheDir)) {
                throw new RuntimeException('The rendered presentation could not be cached.');
            }

            return $slideCount;
        } finally {
            if (is_dir($workingDir)) {
                $this->removeDirectory($workingDir);
            }
        }
    }

    private function cachedSlideCount(string $cacheDir): ?int {
        $manifest = $cacheDir . DIRECTORY_SEPARATOR . 'slide-count';
        if (!is_file($manifest) || !is_file($cacheDir . DIRECTORY_SEPARATOR . 'presentation.pdf')) {
            return null;
        }

        $count = (int)trim((string)file_get_contents($manifest));
        if ($count < 1 || $count > self::MAX_SLIDES) {
            return null;
        }

        $firstSlide = $cacheDir . DIRECTORY_SEPARATOR . 'slide-001.png';
        if (!is_file($firstSlide) || filesize($firstSlide) === 0) {
            return null;
        }

        return $count;
    }

    private function renderSlide(string $cacheDir, int $slide, ?float $deadline = null): void {
        $pdfPath = $cacheDir . DIRECTORY_SEPARATOR . 'presentation.pdf';
        if (!is_file($pdfPath)) {
            throw new RuntimeException('The cached presentation PDF is unavailable.');
        }

        $target = $cacheDir . DIRECTORY_SEPARATOR . sprintf('slide-%03d.png', $slide);
        $temporaryPrefix = $cacheDir . DIRECTORY_SEPARATOR . 'render-' . bin2hex(random_bytes(8));
        $this->runCommand([
            $this->findExecutable('pdftoppm'),
            '-png',
            '-r',
            (string)self::RENDER_DPI,
            '-scale-to',
            (string)self::MAX_IMAGE_DIMENSION,
            '-f',
            (string)$slide,
            '-l',
            (string)$slide,
            '-singlefile',
            $pdfPath,
            $temporaryPrefix,
        ], $deadline ?? (microtime(true) + self::MAX_RENDER_SECONDS));

        $temporaryImage = $temporaryPrefix . '.png';
        if (!is_file($temporaryImage) || filesize($temporaryImage) === 0) {
            throw new RuntimeException('The requested slide could not be rendered.');
        }
        if (!rename($temporaryImage, $target)) {
            @unlink($temporaryImage);
            throw new RuntimeException('The rendered slide could not be cached.');
        }
    }

    private function findExecutable(string $name): string {
        foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $directory) {
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Presentation rendering requires LibreOffice Impress and Poppler (soffice, pdfinfo, pdftoppm). Install them on this server.');
    }

    private function runCommand(array $command, float $deadline): string {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('Presentation rendering requires PHP proc_open to be enabled.');
        }

        $outputPath = tempnam($this->cacheRoot, 'command-');
        if ($outputPath === false) {
            throw new RuntimeException('Could not prepare the presentation renderer.');
        }
        $output = fopen($outputPath, 'w+');
        if ($output === false) {
            @unlink($outputPath);
            throw new RuntimeException('Could not prepare the presentation renderer.');
        }

        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => $output,
            2 => $output,
        ], $pipes, null, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            fclose($output);
            @unlink($outputPath);
            throw new RuntimeException('Could not start the presentation renderer.');
        }

        $exitCode = null;
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process, 9);
                proc_close($process);
                fclose($output);
                @unlink($outputPath);
                throw new RuntimeException('Rendering this presentation took too long.');
            }
            usleep(100000);
        }

        proc_close($process);
        rewind($output);
        $result = stream_get_contents($output);
        fclose($output);
        @unlink($outputPath);

        if ($exitCode !== 0) {
            $details = trim((string)$result);
            error_log('Presentation renderer command failed: ' . $details);
            if (stripos($details, 'soffice') !== false || stripos($details, 'not found') !== false) {
                throw new RuntimeException('Presentation rendering is not installed on this server. Install LibreOffice and Poppler tools.');
            }
            throw new RuntimeException('The presentation renderer could not process this file.');
        }

        return (string)$result;
    }

    private function ensureDirectory(string $directory): void {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('The presentation preview cache could not be created.');
        }
    }

    private function removeDirectory(string $directory): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($directory);
    }
}
