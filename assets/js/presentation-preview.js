(function () {
    const previewVersion = '4';
    const loading = document.getElementById('pptxLoading');
    const badge = document.getElementById('pptxSlideCountBadge');
    const controls = document.getElementById('pptxViewModes');
    const container = document.getElementById('pptxVisualContainer');
    const navigation = document.getElementById('pptxDeckNav');
    const thumbnails = document.getElementById('pptxDeckThumbnails');
    const slidesContainer = document.getElementById('pptxSlides');
    const fallback = document.getElementById('pptxFallback');
    const fallbackMessage = document.getElementById('pptxFallbackMsg');
    const previous = document.getElementById('pptxPrevBtn');
    const next = document.getElementById('pptxNextBtn');
    const indicator = document.getElementById('pptxDeckIndicator');
    const stackedButton = document.getElementById('pptxBtnStacked');
    const deckButton = document.getElementById('pptxBtnDeck');
    const fullscreenButton = document.getElementById('pptxBtnFullscreen');
    const mainCard = document.getElementById('pptxMainCard');

    if (!loading || !slidesContainer) return;

    let slideCards = [];
    let activeSlide = 0;
    let mode = 'stacked';

    function showFallback(message) {
        loading.style.display = 'none';
        if (fallbackMessage) fallbackMessage.textContent = message;
        if (fallback) fallback.style.display = 'block';
    }

    function showSlide(index) {
        if (!slideCards.length) return;
        activeSlide = Math.max(0, Math.min(index, slideCards.length - 1));
        slideCards.forEach(function (card, slideIndex) {
            card.style.display = mode === 'stacked' || slideIndex === activeSlide ? 'block' : 'none';
        });
        if (mode === 'deck') {
            loadSlideImage(activeSlide);
            loadSlideImage(activeSlide + 1);
        }
        if (indicator) indicator.textContent = 'Slide ' + (activeSlide + 1) + ' of ' + slideCards.length;
        if (previous) previous.disabled = activeSlide === 0;
        if (next) next.disabled = activeSlide === slideCards.length - 1;
        thumbnails.querySelectorAll('button').forEach(function (button, slideIndex) {
            button.classList.toggle('active', slideIndex === activeSlide);
        });
        const activeThumbnail = thumbnails.querySelector('.active');
        if (mode === 'deck' && activeThumbnail) {
            activeThumbnail.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    function loadSlideImage(index) {
        const card = slideCards[index];
        const image = card && card.querySelector('img[data-src]');
        if (!image) return;
        image.src = image.dataset.src;
        image.removeAttribute('data-src');
    }

    function setMode(nextMode) {
        mode = nextMode;
        stackedButton.className = mode === 'stacked' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
        deckButton.className = mode === 'deck' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
        navigation.style.display = mode === 'deck' ? 'flex' : 'none';
        thumbnails.style.display = mode === 'deck' ? 'flex' : 'none';
        showSlide(activeSlide);
    }

    function renderSlides(result) {
        if (!result || !Array.isArray(result.slides) || result.slides.length !== result.count || !result.count) {
            throw new Error('The presentation did not contain any renderable slides.');
        }

        result.slides.forEach(function (url, index) {
            const card = document.createElement('figure');
            card.className = 'pptx-slide-card';
            card.setAttribute('data-slide-index', String(index));

            const caption = document.createElement('figcaption');
            caption.className = 'pptx-slide-card-header';
            caption.textContent = 'Slide ' + (index + 1) + ' of ' + result.count;

            const image = document.createElement('img');
            image.className = 'pptx-slide-image';
            image.alt = 'Slide ' + (index + 1);
            image.loading = 'lazy';
            image.decoding = 'async';
            image.dataset.src = url;
            image.addEventListener('error', function () {
                const errorMessage = document.createElement('p');
                errorMessage.className = 'pptx-slide-error';
                errorMessage.setAttribute('role', 'alert');
                errorMessage.textContent = 'Slide ' + (index + 1) + ' could not be loaded. Reload the document; if it continues, check the slide request status in the browser Network panel.';
                card.appendChild(errorMessage);
            }, { once: true });

            card.append(caption, image);
            slidesContainer.appendChild(card);
            slideCards.push(card);
            if (index === 0) loadSlideImage(index);

            const thumbnail = document.createElement('button');
            thumbnail.type = 'button';
            thumbnail.className = 'pptx-thumb-btn';
            thumbnail.textContent = String(index + 1);
            thumbnail.setAttribute('aria-label', 'Show slide ' + (index + 1));
            thumbnail.addEventListener('click', function () {
                showSlide(index);
            });
            thumbnails.appendChild(thumbnail);
        });

        loading.style.display = 'none';
        controls.style.display = 'flex';
        container.style.display = 'block';
        thumbnails.style.display = 'none';
        badge.textContent = result.count + (result.count === 1 ? ' Slide' : ' Slides');
        badge.style.display = 'inline-block';
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        loadSlideImage(Number(entry.target.dataset.slideIndex));
                        observer.unobserve(entry.target);
                    }
                });
            }, { root: container, rootMargin: '400px 0px' });
            slideCards.forEach(function (card) { observer.observe(card); });
        } else {
            slideCards.forEach(function (_, index) { loadSlideImage(index); });
        }
        showSlide(0);
    }

    previous.addEventListener('click', function () { showSlide(activeSlide - 1); });
    next.addEventListener('click', function () { showSlide(activeSlide + 1); });
    stackedButton.addEventListener('click', function () { setMode('stacked'); });
    deckButton.addEventListener('click', function () { setMode('deck'); });
    fullscreenButton.addEventListener('click', function () {
        if (!document.fullscreenElement) {
            if (mainCard.requestFullscreen) mainCard.requestFullscreen();
            else if (mainCard.webkitRequestFullscreen) mainCard.webkitRequestFullscreen();
        } else if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        }
    });

    window.addEventListener('keydown', function (event) {
        if (mode !== 'deck') return;
        if (event.key === 'ArrowLeft') showSlide(activeSlide - 1);
        if (event.key === 'ArrowRight') showSlide(activeSlide + 1);
    });

    const requestController = new AbortController();
    const requestTimeout = window.setTimeout(function () {
        requestController.abort();
    }, 150000);

    fetch(mainCard.dataset.previewUrl, {
        credentials: 'same-origin',
        signal: requestController.signal
    })
        .then(function (response) {
            if (!response.headers.get('content-type') || !response.headers.get('content-type').includes('application/json')) {
                throw new Error('The preview endpoint returned a non-JSON response (HTTP ' + response.status + '). It may have redirected to login or encountered a server error; inspect the preview request in the browser Network panel.');
            }
            const responseVersion = response.headers.get('X-Trackify-Preview-Version');
            if (responseVersion !== previewVersion) {
                throw new Error('The preview page and server endpoint versions do not match. Reload the page or redeploy the latest application files.');
            }
            return response.json().then(function (data) {
                if (!response.ok) throw new Error(data.error || 'The preview endpoint returned HTTP ' + response.status + '.');
                return data;
            });
        })
        .then(renderSlides)
        .catch(function (error) {
            console.error('Presentation preview error:', {
                version: previewVersion,
                url: mainCard.dataset.previewUrl,
                message: error.message,
                error: error
            });
            const message = error.name === 'AbortError'
                ? 'The preview took too long to respond. Check that LibreOffice and Poppler are installed, then reload the document.'
                : error.message || 'The presentation could not be rendered. Please download it to view.';
            showFallback(message);
        })
        .finally(function () {
            window.clearTimeout(requestTimeout);
        });
})();
