import { mountOutfitImage } from './outfit-composition.js';

const dateLabel = new Intl.DateTimeFormat('es-CL', { weekday: 'long', month: 'short', day: 'numeric', timeZone: 'UTC' });

export function mountRecommendations(root, weatherReady) {
    const categories = JSON.parse(root.dataset.categoryLabels);
    const form = root.querySelector('[data-outfit-form]');
    const button = form.querySelector('[type=submit]');
    const style = form.elements.namedItem('style');
    const status = root.querySelector('[data-outfit-status]');
    const location = root.querySelector('[data-outfit-location]');
    const closet = root.querySelector('[data-outfit-closet]');
    const list = root.querySelector('[data-outfit-list]');
    const dayTemplate = root.querySelector('[data-outfit-day]');
    const visibleOutfits = new IntersectionObserver((entries, observer) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                entry.target.dispatchEvent(new Event('compose-outfit'));
            }
        }
    }, { rootMargin: '160px' });

    function renderDay(day, cutoutCache) {
        const fragment = dayTemplate.content.cloneNode(true);
        const date = fragment.querySelector('time');
        date.dateTime = day.date;
        date.textContent = dateLabel.format(new Date(day.date + 'T12:00:00Z'));
        const temperature = root.dataset.period === 'week' && day.min !== null && day.max !== null
            ? day.min + '° a ' + day.max + ' °C'
            : 'Sensación térmica: ' + day.temperature + ' °C';
        fragment.querySelector('[data-outfit-weather]').textContent = temperature + (day.rain ? ' · Posible precipitación' : '');
        fragment.querySelector('[data-outfit-garments]').append(...day.garments.map((garment) => {
            const item = document.createElement('li');
            item.textContent = garment.name;
            return item;
        }));
        fragment.querySelector('[data-outfit-reason]').textContent = day.reason;

        if (day.missing.length) {
            const missing = fragment.querySelector('[data-outfit-missing]');
            missing.textContent = 'Para completar el outfit, agrega prendas de estas categorías: ' + day.missing.map((key) => categories[key].toLowerCase()).join(', ') + '.';
            missing.hidden = false;
        }

        const composition = fragment.querySelector('[data-outfit-composition]');
        const composeImage = mountOutfitImage(composition, day, cutoutCache);
        if (root.dataset.period === 'today') {
            queueMicrotask(composeImage);
        } else {
            const article = composition.closest('article');
            article.addEventListener('compose-outfit', composeImage, { once: true });
            visibleOutfits.observe(article);
        }

        return fragment;
    }

    async function generate({ automatic = false } = {}) {
        if (button.disabled) {
            return;
        }

        const body = new FormData(form);
        if (automatic) {
            body.set('automatic', '1');
        }
        button.disabled = true;
        style.disabled = true;
        location.hidden = true;
        closet.hidden = true;
        status.textContent = 'Combinando tus prendas con el pronóstico…';

        try {
            await weatherReady;
            const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body });
            const data = await response.json();

            if (!response.ok) {
                location.hidden = data.code !== 'location_required';
                closet.hidden = data.code !== 'closet_empty';
                const message = {
                    401: 'Vuelve a iniciar sesión para generar outfits.',
                    419: 'Tu sesión venció. Actualiza la página para continuar.',
                    429: 'Alcanzaste el límite de solicitudes. Prueba en un minuto.',
                }[response.status];
                status.textContent = message ?? (response.status < 500 || response.status === 502 ? data.message : 'No pudimos generar el outfit. Inténtalo de nuevo.');
                return;
            }

            visibleOutfits.disconnect();
            const cutoutCache = new Map();
            list.replaceChildren(...data.outfits.map((day) => renderDay(day, cutoutCache)));
            const requestedStyle = body.get('style');
            const matchesStyle = data.outfits.some((outfit) => outfit.garments.some((garment) => garment.style === requestedStyle));
            if (data.source === 'groq') {
                status.textContent = 'Recomendación generada con IA.';
            } else if (requestedStyle && !matchesStyle) {
                status.textContent = 'Mostramos prendas adecuadas para el clima. Puedes etiquetar más prendas con este estilo en Mi clóset.';
            } else {
                status.textContent = 'Recomendación basada en el clima y tus prendas.';
            }
        } catch {
            status.textContent = 'No pudimos generar el outfit. Inténtalo de nuevo.';
        } finally {
            button.disabled = false;
            style.disabled = false;
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        generate();
    });

    if (root.dataset.period === 'today') {
        generate({ automatic: true });
    }
}
