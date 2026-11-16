import { dataStatus, getJson } from './data-view.js';

export function mountCloset(root) {
    const status = dataStatus(root);
    const categories = new Map([...root.querySelectorAll('[data-category-link]')].map((link) => [
        link.dataset.categoryLink,
        link.textContent.trim(),
    ]));
    const category = root.dataset.category;
    const list = root.querySelector('[data-closet-items]');
    const empty = root.querySelector('[data-closet-empty]');
    const template = root.querySelector('[data-closet-item]');
    const pagination = root.querySelector('[data-closet-pagination]');
    const currentUrl = new URL(window.location.href);

    function pageUrl(page) {
        const url = new URL(currentUrl.pathname, currentUrl.origin);
        if (category) {
            url.searchParams.set('category', category);
        }
        if (page > 1) {
            url.searchParams.set('page', page);
        }
        return url;
    }

    function setPageLink(selector, page, available) {
        const link = pagination.querySelector(selector);
        link.hidden = !available;
        link.href = pageUrl(page);
    }

    async function load({ fromRetry = false } = {}) {
        status.show('Cargando tus prendas…', { restoreFocus: fromRetry });
        const endpoint = new URL(root.dataset.closet, currentUrl.origin);
        endpoint.search = pageUrl(currentUrl.searchParams.get('page')).search;

        try {
            const { data, meta } = await getJson(endpoint);

            if (!Array.isArray(data) || !meta) {
                throw new Error('La API no devolvió una lista de prendas.');
            }

            list.replaceChildren(...data.map((item) => {
                const fragment = template.content.cloneNode(true);
                fragment.querySelector('[data-item-category]').textContent = categories.get(item.category) ?? 'Prenda';
                fragment.querySelector('[data-item-name]').textContent = item.name;
                const color = fragment.querySelector('[data-item-color]');
                color.textContent = item.color ?? '';
                color.hidden = !item.color;
                return fragment;
            }));
            list.hidden = data.length === 0;
            empty.hidden = meta.total > 0;

            if (meta.current_page > meta.last_page && meta.total > 0) {
                status.show('Esta página ya no existe. Usa «Anterior» para volver a las prendas.');
            } else if (data.length) {
                status.show('Mostrando ' + meta.from + '–' + meta.to + ' de ' + meta.total + (meta.total === 1 ? ' prenda.' : ' prendas.'));
            } else {
                status.show('No se encontraron prendas.', { visuallyHidden: true });
            }

            setPageLink('[data-page-previous]', Math.min(meta.current_page - 1, meta.last_page), meta.current_page > 1);
            setPageLink('[data-page-next]', meta.current_page + 1, meta.current_page < meta.last_page);
            pagination.querySelector('[data-page-label]').textContent = 'Página ' + meta.current_page + ' de ' + meta.last_page;
            pagination.querySelector('[data-page-label]').hidden = meta.current_page > meta.last_page;
            pagination.hidden = meta.total === 0 || (meta.last_page === 1 && meta.current_page === 1);
        } catch (error) {
            if (error.status === 401) {
                status.show('Tu sesión terminó. Vuelve a entrar para ver tus prendas.', { sessionExpired: true });
            } else {
                status.show('No pudimos cargar tus prendas. Inténtalo de nuevo.', { canRetry: true });
            }
        }
    }

    status.retry.addEventListener('click', () => load({ fromRetry: true }));
    load();
}
