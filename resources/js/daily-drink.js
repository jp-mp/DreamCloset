export function mountDailyDrink(card) {
    const form = card.querySelector('form');
    const button = form.querySelector('button');
    const heading = card.querySelector('h2');
    const recipe = card.querySelector('[data-daily-drink-recipe]');
    const error = card.querySelector('[data-daily-drink-error]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (button.getAttribute('aria-disabled') === 'true') {
            return;
        }

        button.setAttribute('aria-disabled', 'true');
        error.textContent = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
            });

            if (!response.ok) {
                throw new Error('Could not load the next drink');
            }

            const drink = await response.json();
            const scrollPosition = window.scrollY;
            heading.textContent = drink.name;
            recipe.innerHTML = drink.recipe;
            window.scrollTo({ top: scrollPosition, behavior: 'instant' });
        } catch {
            error.textContent = 'No se pudo cambiar el trago. Inténtalo de nuevo.';
        } finally {
            button.removeAttribute('aria-disabled');
        }
    });
}
