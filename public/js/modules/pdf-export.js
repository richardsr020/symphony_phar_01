(function (global) {
    const currentScript = document.currentScript;
    const scriptSrc = currentScript && currentScript.src ? currentScript.src : '';
    const basePrefix = scriptSrc.includes('/public/js/modules/pdf-export.js')
        ? scriptSrc.split('/public/js/modules/pdf-export.js')[0]
        : '';
    const withBase = (path) => `${basePrefix}${path}`;
    const LIBRARY_CANDIDATES = [
        withBase('/public/js/modules/html2pdf.min.js'),
        withBase('/public/js/libs/html2pdf.bundle.min.js'),
    ];

    let libraryLoadPromise = null;

    const toAbsoluteUrl = (src) => {
        try {
            return new URL(String(src || '').trim(), global.location.href).toString();
        } catch (error) {
            return String(src || '').trim();
        }
    };

    const blobToDataUrl = (blob) => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ''));
        reader.onerror = () => reject(new Error('blob_read_failed'));
        reader.readAsDataURL(blob);
    });

    const waitForImageReady = (image) => new Promise((resolve) => {
        if (!image) {
            resolve();
            return;
        }

        if (image.complete && image.naturalWidth > 0) {
            resolve();
            return;
        }

        const done = () => resolve();
        image.addEventListener('load', done, { once: true });
        image.addEventListener('error', done, { once: true });
        global.setTimeout(done, 4000);
    });

    const inlineImagesForExport = async (root) => {
        if (!root) {
            return () => {};
        }

        const images = Array.from(root.querySelectorAll('img'));
        const restoreStack = [];

        await Promise.all(images.map(async (image) => {
            const rawSrc = String(image.getAttribute('src') || '').trim();
            if (rawSrc === '') {
                return;
            }

            const originalSrc = image.getAttribute('src');
            const originalSrcset = image.getAttribute('srcset');
            restoreStack.push(() => {
                if (originalSrc === null) {
                    image.removeAttribute('src');
                } else {
                    image.setAttribute('src', originalSrc);
                }

                if (originalSrcset === null) {
                    image.removeAttribute('srcset');
                } else {
                    image.setAttribute('srcset', originalSrcset);
                }
            });

            image.loading = 'eager';
            image.decoding = 'sync';
            await waitForImageReady(image);

            if (rawSrc.startsWith('data:')) {
                return;
            }

            const absoluteUrl = toAbsoluteUrl(rawSrc);

            try {
                const response = await global.fetch(absoluteUrl, {
                    credentials: 'same-origin',
                    cache: 'force-cache',
                });

                if (!response.ok) {
                    throw new Error(`image_fetch_failed:${response.status}`);
                }

                const blob = await response.blob();
                const dataUrl = await blobToDataUrl(blob);
                if (dataUrl === '') {
                    throw new Error('image_data_url_empty');
                }

                image.setAttribute('src', dataUrl);
                image.removeAttribute('srcset');
                await waitForImageReady(image);
            } catch (error) {
                console.warn('Unable to inline image for PDF export', absoluteUrl, error);
                image.setAttribute('crossorigin', 'anonymous');
                image.setAttribute('referrerpolicy', 'no-referrer');
            }
        }));

        return () => {
            while (restoreStack.length > 0) {
                const restore = restoreStack.pop();
                if (typeof restore === 'function') {
                    restore();
                }
            }
        };
    };

    const defaultNotify = (message, type = 'info') => {
        if (global.Symphony && typeof global.Symphony.showNotification === 'function') {
            global.Symphony.showNotification(message, type);
            return;
        }
        global.alert(message);
    };

    const mergeOnClone = (customOnClone) => (clonedDocument) => {
        clonedDocument.querySelectorAll('script').forEach((script) => script.remove());
        clonedDocument.querySelectorAll('img').forEach((image) => {
            const src = String(image.getAttribute('src') || '').trim();
            if (src === '') {
                image.remove();
                return;
            }
            image.setAttribute('crossorigin', 'anonymous');
            image.setAttribute('referrerpolicy', 'no-referrer');
            image.loading = 'eager';
            image.decoding = 'sync';
        });

        if (typeof customOnClone === 'function') {
            customOnClone(clonedDocument);
        }
    };

    const injectScript = (src) => new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[data-pdf-lib="${src}"]`);
        if (existing) {
            if (existing.dataset.loaded === '1') {
                resolve();
                return;
            }
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error(`load_failed:${src}`)), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.dataset.pdfLib = src;
        script.addEventListener('load', () => {
            script.dataset.loaded = '1';
            resolve();
        }, { once: true });
        script.addEventListener('error', () => reject(new Error(`load_failed:${src}`)), { once: true });
        document.head.appendChild(script);
    });

    const ensureLibrary = async () => {
        if (typeof global.html2pdf === 'function') {
            return global.html2pdf;
        }
        if (libraryLoadPromise) {
            return libraryLoadPromise;
        }

        libraryLoadPromise = (async () => {
            for (const candidate of LIBRARY_CANDIDATES) {
                try {
                    await injectScript(candidate);
                    if (typeof global.html2pdf === 'function') {
                        return global.html2pdf;
                    }
                } catch (error) {
                    console.warn('PDF library candidate failed to load', candidate, error);
                }
            }
            throw new Error('html2pdf_unavailable');
        })();

        return libraryLoadPromise;
    };

    const download = async (config) => {
        const options = config || {};
        const button = options.button || null;
        const sheet = options.element || options.sheet || null;
        const fileName = String(options.filename || 'document.pdf').trim() || 'document.pdf';
        const notify = typeof options.notify === 'function' ? options.notify : defaultNotify;
        const fallbackUrl = String(options.fallbackUrl || '').trim();
        const autoDownload = options.autoDownload === true;
        const onSuccess = typeof options.onSuccess === 'function' ? options.onSuccess : null;
        const html2pdfOptions = options.html2pdf || {};

        if (!sheet) {
            notify('Le contenu a exporter est introuvable.', 'error');
            return false;
        }

        const initialButtonHtml = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generation...';
        }

        let saveSucceeded = false;
        let restoreImages = () => {};

        try {
            await ensureLibrary();
            restoreImages = await inlineImagesForExport(sheet);
            await global.html2pdf()
                .set({
                    margin: 0,
                    filename: fileName,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        allowTaint: false,
                        imageTimeout: 15000,
                        backgroundColor: '#ffffff',
                        logging: false,
                        onclone: mergeOnClone(html2pdfOptions.html2canvas?.onclone),
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
                    ...html2pdfOptions,
                })
                .from(sheet)
                .save();

            saveSucceeded = true;
        } catch (error) {
            console.error('PDF export failed', error);
            if (fallbackUrl !== '' && !autoDownload) {
                global.location.href = fallbackUrl;
                return false;
            }
            notify('Impossible de telecharger le PDF pour le moment.', 'error');
            return false;
        } finally {
            try {
                restoreImages();
            } catch (error) {
                console.warn('Unable to restore original images after PDF export', error);
            }

            if (button) {
                button.disabled = false;
                button.innerHTML = initialButtonHtml;
            }
        }

        if (saveSucceeded && onSuccess) {
            try {
                await onSuccess();
            } catch (error) {
                console.warn('PDF post-download hook failed', error);
            }
        }

        notify('PDF telecharge avec succes.', 'success');
        return true;
    };

    global.SymphonyPdfExport = {
        ensureLibrary,
        download,
    };
})(window);
