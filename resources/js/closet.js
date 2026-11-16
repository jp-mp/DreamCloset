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
    const dialog = root.querySelector('[data-delete-dialog]');
    const deleteName = dialog.querySelector('[data-delete-name]');
    const deleteStatus = dialog.querySelector('[data-delete-status]');
    const deleteConfirm = dialog.querySelector('[data-delete-confirm]');
    const deleteCancel = dialog.querySelector('[data-delete-cancel]');
    let selectedItem;

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

    dialog.addEventListener('cancel', (event) => {
        if (deleteConfirm.disabled) {
            event.preventDefault();
        }
    });

    deleteConfirm.addEventListener('click', async () => {
        deleteConfirm.disabled = true;
        deleteCancel.disabled = true;
        deleteStatus.textContent = 'Eliminando prenda…';

        try {
            const response = await fetch(root.dataset.deleteUrl.replace('__item__', selectedItem.id), {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf },
            });

            if (response.status === 401 || response.status === 419) {
                deleteStatus.textContent = 'Tu sesión venció. Vuelve a iniciar sesión para eliminar la prenda.';
                return;
            }
            if (!response.ok && response.status !== 404) {
                throw new Error('No pudimos eliminar la prenda.');
            }

            dialog.close();
            await load({ fromRetry: true });
        } catch {
            deleteStatus.textContent = 'No pudimos confirmar la eliminación. Inténtalo de nuevo.';
        } finally {
            deleteConfirm.disabled = false;
            deleteCancel.disabled = false;
        }
    });

    function mountActions(fragment, item) {
        const edit = fragment.querySelector('[data-item-edit]');
        edit.href = item.edit_url;
        edit.setAttribute('aria-label', 'Editar ' + item.name);

        const remove = fragment.querySelector('[data-item-delete]');
        remove.setAttribute('aria-label', 'Eliminar ' + item.name);
        remove.addEventListener('click', () => {
            selectedItem = item;
            deleteName.textContent = item.name;
            deleteStatus.textContent = '';
            dialog.showModal();
        });
    }

    function mountStyle(fragment, item) {
        const select = fragment.querySelector('[data-item-style]');
        const status = fragment.querySelector('[data-style-status]');
        const url = root.dataset.styleUrl.replace('__item__', item.id);
        select.value = item.style ?? '';
        select.setAttribute('aria-label', 'Estilo de ' + item.name);

        select.addEventListener('change', async () => {
            select.disabled = true;
            status.textContent = 'Guardando estilo…';
            try {
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf },
                    body: JSON.stringify({ style: select.value || null }),
                });

                if (!response.ok) {
                    throw new Error('No pudimos guardar el estilo.');
                }

                const { data } = await response.json();
                item.style = data.style;
                status.textContent = 'Estilo guardado.';
            } catch {
                select.value = item.style ?? '';
                status.textContent = 'No pudimos guardar el estilo. Inténtalo de nuevo.';
            } finally {
                select.disabled = false;
            }
        });
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
                if (item.photo_url) {
                    const photo = fragment.querySelector('[data-item-photo]');
                    const placeholder = fragment.querySelector('[data-item-placeholder]');
                    photo.addEventListener('error', () => {
                        photo.hidden = true;
                        placeholder.hidden = false;
                    });
                    photo.hidden = false;
                    placeholder.hidden = true;
                    photo.src = item.photo_url;
                }
                mountStyle(fragment, item);
                mountActions(fragment, item);
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
