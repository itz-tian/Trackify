# Trackify

## PowerPoint slide previews

PPT and PPTX previews are rendered on the server to PNG slide images. The
Docker image installs LibreOffice Impress and Poppler (`pdfinfo`, `pdftoppm`);
the PHP process runs as the unprivileged `www-data` user. Slide images are
rendered on demand and cached outside the public web root by the presentation's
SHA-256 hash. Opening a deck renders only its first slide; scrolling or
navigating requests more PNGs from the cached PDF, without reconverting it.

For a local PHP/XAMPP installation, install LibreOffice and Poppler and ensure
`soffice`, `pdfinfo`, and `pdftoppm` are available on PHP's `PATH`. Rendering
supports presentations with up to 100 slides.
