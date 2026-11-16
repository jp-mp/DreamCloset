const imageTypes = ['image/jpeg', 'image/png', 'image/webp'];

export function mountGarmentPhoto(form) {
    const input = form.elements.namedItem('photo');
    const previews = form.querySelector('[data-photo-previews]');
    const actions = form.querySelector('[data-photo-actions]');
    const originalImage = form.querySelector('[data-photo-original]');
    const originalLabel = form.querySelector('[data-photo-source-label]');
    const cutoutFigure = form.querySelector('[data-photo-cutout]');
    const cutoutImage = cutoutFigure.querySelector('img');
    const removeBackground = form.querySelector('[data-remove-background]');
    const restorePhoto = form.querySelector('[data-restore-photo]');
    const useOriginal = form.querySelector('[data-choose-original]');
    const useCutout = form.querySelector('[data-choose-cutout]');
    const status = form.querySelector('[data-photo-status]');
    const submit = form.querySelector('[type=submit]');
    const savedPhoto = form.dataset.savedPhoto || null;
    let original;
    let cutout;
    let selected;
    let pending;

    function clearPreview(image) {
        if (image.src.startsWith('blob:')) {
            URL.revokeObjectURL(image.src);
        }
        image.removeAttribute('src');
    }

    function choose(image) {
        selected = image;
        useOriginal.setAttribute('aria-pressed', String(selected === original));
        useCutout.setAttribute('aria-pressed', String(Boolean(cutout && selected === cutout)));

        if (selected === savedPhoto && savedPhoto) {
            status.textContent = 'Se conservará la foto guardada.';
        } else if (selected) {
            status.textContent = selected === original ? 'Se guardará la foto original.' : 'Se guardará la foto sin fondo.';
        } else {
            status.textContent = savedPhoto ? 'La foto se quitará al guardar los cambios.' : '';
        }
    }

    function selectPhoto(photo) {
        if (pending) {
            pending.abort();
            pending = undefined;
            submit.disabled = false;
        }
        removeBackground.disabled = false;
        clearPreview(originalImage);
        clearPreview(cutoutImage);
        cutout = undefined;
        original = photo;
        selected = photo;
        cutoutFigure.hidden = true;
        useOriginal.hidden = true;
        originalLabel.textContent = photo && photo === savedPhoto ? 'Foto guardada' : 'Original';
        useOriginal.textContent = photo && photo === savedPhoto ? 'Usar foto guardada' : 'Usar original';

        const invalid = photo instanceof File && ((!imageTypes.includes(photo.type) && photo.type !== '') || photo.size > 5 * 1024 * 1024);
        actions.hidden = !photo;
        previews.hidden = !photo || invalid;
        removeBackground.hidden = invalid;
        restorePhoto.hidden = !savedPhoto || photo === savedPhoto;
        const message = invalid ? 'Selecciona una foto JPG, PNG o WebP de hasta 5 MB.' : '';
        input.setCustomValidity(message);

        if (invalid) {
            status.textContent = message;
            return;
        }

        if (photo) {
            originalImage.src = photo instanceof File ? URL.createObjectURL(photo) : photo;
        }
        choose(photo);
    }

    input.addEventListener('change', () => selectPhoto(input.files[0] ?? savedPhoto));
    form.querySelector('[data-remove-photo]').addEventListener('click', () => {
        input.value = '';
        selectPhoto(null);
        input.focus();
    });
    restorePhoto.addEventListener('click', () => {
        input.value = '';
        selectPhoto(savedPhoto);
        input.focus();
    });
    useOriginal.addEventListener('click', () => choose(original));
    useCutout.addEventListener('click', () => choose(cutout));

    removeBackground.addEventListener('click', async () => {
        if (!original || pending || submit.disabled) {
            return;
        }

        const request = new AbortController();
        pending = request;
        const timeout = setTimeout(() => request.abort('timeout'), 45000);
        removeBackground.disabled = true;
        submit.disabled = true;
        status.textContent = 'Quitando el fondo…';

        try {
            let photo = original;
            if (typeof photo === 'string') {
                const saved = await fetch(photo, { signal: request.signal });
                if (!saved.ok) {
                    throw new Error('No pudimos cargar la foto guardada. Puedes seleccionar otra foto.');
                }
                photo = await saved.blob();
            }

            const body = new FormData();
            body.append('photo', photo);
            body.append('_token', form.elements.namedItem('_token').value);
            const response = await fetch(form.dataset.photoEndpoint, {
                method: 'POST',
                headers: { Accept: 'application/json, image/png' },
                signal: request.signal,
                body,
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                const message = {
                    401: 'Tu sesión venció. Vuelve a iniciar sesión.',
                    419: 'Tu sesión venció. Actualiza la página para continuar.',
                    413: 'La foto supera el límite de subida. Prueba con una imagen más pequeña.',
                    422: payload.errors?.photo?.[0],
                    429: 'El recorte está ocupado. Intenta en unos segundos.',
                    503: payload.message,
                }[response.status];
                throw new Error(message ?? 'El recorte no está disponible. Puedes conservar la foto o volver a intentar.');
            }

            const blob = await response.blob();
            if (pending !== request) {
                return;
            }

            cutout = new File([blob], 'prenda-sin-fondo.png', { type: 'image/png' });
            cutoutImage.src = URL.createObjectURL(cutout);
            cutoutFigure.hidden = false;
            useOriginal.hidden = false;
            removeBackground.hidden = true;
            choose(cutout);
            status.textContent = 'Recorte listo. Pulsa Guardar para usar la foto sin fondo.';
            useCutout.focus({ preventScroll: true });
        } catch (error) {
            if (pending === request) {
                if (request.signal.reason === 'timeout') {
                    status.textContent = 'El recorte tardó demasiado. Puedes volver a intentarlo o conservar la foto original.';
                } else {
                    status.textContent = error instanceof TypeError ? 'Se perdió la conexión. Vuelve a intentar el recorte.' : error.message;
                }
            }
        } finally {
            clearTimeout(timeout);
            if (pending === request) {
                pending = undefined;
                removeBackground.disabled = false;
                submit.disabled = false;
            }
        }
    });

    selectPhoto(savedPhoto);

    return (body) => {
        body.delete('photo');
        if (selected instanceof File) {
            body.set('photo', selected);
        } else if (savedPhoto && !selected) {
            body.set('remove_photo', '1');
        }
    };
}
