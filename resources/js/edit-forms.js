function saveForm(form, saved) {
    const submit = form.querySelector('[type=submit]');
    const status = form.querySelector('[data-save-status]');
    const fieldErrors = [];

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (submit.disabled) {
            return;
        }

        for (const { field, message, description } of fieldErrors.splice(0)) {
            field.removeAttribute('aria-invalid');
            if (description) {
                field.setAttribute('aria-describedby', description);
            } else {
                field.removeAttribute('aria-describedby');
            }
            message.remove();
        }

        submit.disabled = true;
        status.textContent = 'Guardando…';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new URLSearchParams(new FormData(form)),
            });

            if (response.status === 422) {
                const { errors } = await response.json();
                for (const [name, messages] of Object.entries(errors ?? {})) {
                    const field = form.elements.namedItem(name);
                    if (!(field instanceof HTMLElement) || field.type === 'hidden') {
                        continue;
                    }

                    const message = document.createElement('p');
                    message.id = field.id + '-api-error';
                    message.className = 'text-sm font-medium text-red-800';
                    message.textContent = messages.join(' ');
                    const description = field.getAttribute('aria-describedby');
                    field.setAttribute('aria-invalid', 'true');
                    field.setAttribute('aria-describedby', [description, message.id].filter(Boolean).join(' '));
                    field.after(message);
                    fieldErrors.push({ field, message, description });
                }
                status.textContent = 'Revisa los datos del formulario e inténtalo de nuevo.';
                fieldErrors[0]?.field.focus();
                return;
            }

            if (response.status === 401 || response.status === 419) {
                status.textContent = 'Tu sesión venció. Vuelve a iniciar sesión para guardar.';
                return;
            }

            if (!response.ok) {
                throw new Error('No se pudo guardar el formulario.');
            }

            status.textContent = saved(await response.json());
        } catch {
            status.textContent = 'No se pudo confirmar el guardado. Revisa si tus cambios aparecen antes de reintentar.';
        } finally {
            submit.disabled = false;
        }
    });
}

export function mountGarmentForm(form) {
    saveForm(form, () => {
        form.reset();
        window.location.replace(form.dataset.returnUrl);
        return 'Prenda guardada.';
    });
}

export function mountLocationForm(form) {
    const savedLocation = document.querySelector('[data-saved-location]');
    const geolocate = form.querySelector('[data-geolocate]');
    const status = form.querySelector('[data-geolocation-status]');
    const { latitude, longitude } = form.elements;

    saveForm(form, ({ data }) => {
        savedLocation.textContent = data.name ?? 'Mi ubicación';
        return 'Ubicación guardada.';
    });

    if (!navigator.geolocation) {
        geolocate.hidden = true;
        return;
    }

    geolocate.addEventListener('click', () => {
        const previousLatitude = latitude.value;
        const previousLongitude = longitude.value;
        geolocate.disabled = true;
        status.textContent = 'Buscando tu ubicación…';

        navigator.geolocation.getCurrentPosition(({ coords }) => {
            geolocate.disabled = false;
            if (latitude.value === previousLatitude && longitude.value === previousLongitude) {
                latitude.value = coords.latitude.toFixed(4);
                longitude.value = coords.longitude.toFixed(4);
                status.textContent = 'Coordenadas listas. Pulsa Guardar ubicación para aplicarlas.';
            } else {
                status.textContent = 'Conservamos las coordenadas que ingresaste.';
            }
        }, () => {
            geolocate.disabled = false;
            status.textContent = 'No pudimos obtener tu ubicación. Puedes escribir las coordenadas manualmente.';
        }, { timeout: 10000 });
    });
}
