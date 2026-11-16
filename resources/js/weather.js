import { dataStatus, getJson } from './data-view.js';

const conditionGroups = [
    [[0, 1], 'Despejado', '☀︎'],
    [[2], 'Parcialmente nublado', '⛅︎'],
    [[3], 'Nublado', '☁︎'],
    [[45, 48], 'Niebla', '≋'],
    [[51, 53, 55, 56, 57], 'Llovizna', '☂︎'],
    [[61, 63, 65, 66, 67, 80, 81, 82], 'Lluvia', '☂︎'],
    [[71, 73, 75, 77, 85, 86], 'Nieve', '❄︎'],
    [[95, 96, 97, 99], 'Tormenta', 'ϟ'],
];

const weekday = new Intl.DateTimeFormat('es-CL', { weekday: 'short', timeZone: 'UTC' });
const shortDate = new Intl.DateTimeFormat('es-CL', { day: 'numeric', month: 'short', timeZone: 'UTC' });
const longDate = new Intl.DateTimeFormat('es-CL', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' });

function condition(code) {
    const group = conditionGroups.find(([codes]) => codes.includes(code));
    return group ? { label: group[1], icon: group[2] } : { label: 'Condición no disponible', icon: '–' };
}

function measurement(value, unit) {
    return Number.isFinite(value) ? Math.round(value) + unit : '–';
}

function forecastDays(daily) {
    if (!Array.isArray(daily?.time) || !daily.time.length) {
        throw new Error('El pronóstico no contiene fechas.');
    }

    return daily.time.slice(0, 7).map((isoDate, index) => {
        const date = new Date(isoDate + 'T00:00:00Z');

        if (Number.isNaN(date.getTime())) {
            throw new Error('El pronóstico contiene una fecha inválida.');
        }

        return {
            isoDate,
            weekday: index === 0 ? 'Hoy' : weekday.format(date).replace('.', ''),
            shortDate: shortDate.format(date),
            longDate: longDate.format(date),
            high: daily.temperature_2m_max?.[index],
            low: daily.temperature_2m_min?.[index],
            rain: daily.precipitation_probability_max?.[index],
            condition: condition(daily.weather_code?.[index]),
        };
    });
}

function fillDays(root, days) {
    const list = root.querySelector('[data-forecast-list]');
    const template = root.querySelector('[data-forecast-day]');

    list.replaceChildren(...days.map((day) => {
        const fragment = template.content.cloneNode(true);
        const time = fragment.querySelector('[data-date]');
        time.dateTime = day.isoDate;
        time.textContent = day.shortDate;
        fragment.querySelector('[data-day]').textContent = day.weekday;
        fragment.querySelector('[data-day]').title = day.longDate;
        fragment.querySelector('[data-icon]').textContent = day.condition.icon;
        fragment.querySelector('[data-condition]').textContent = day.condition.label;
        fragment.querySelector('[data-high]').textContent = measurement(day.high, '°');
        fragment.querySelector('[data-low]').textContent = measurement(day.low, '°');
        fragment.querySelector('[data-rain]').textContent = measurement(day.rain, '%');
        if (day.rain >= 50) {
            fragment.firstElementChild.classList.replace('bg-white', 'bg-[#e8eeeb]');
        }
        return fragment;
    }));

    list.hidden = false;
}

function fillCurrent(panel, current, today) {
    const currentCondition = condition(current.weather_code);
    panel.querySelector('[data-temperature]').textContent = measurement(current.temperature_2m, '°C');
    panel.querySelector('[data-current-icon]').textContent = currentCondition.icon;
    panel.querySelector('[data-current-condition]').textContent = currentCondition.label;
    panel.querySelector('[data-current-range]').textContent = measurement(today.high, '°') + ' / ' + measurement(today.low, '°');
    panel.querySelector('[data-current-rain]').textContent = measurement(today.rain, '%');
    panel.querySelector('[data-current-wind]').textContent = measurement(current.wind_speed_10m, ' km/h');
    panel.hidden = false;
}

function fillChart(chart, template, days) {
    const highs = days.map((day) => day.high).filter(Number.isFinite);
    if (highs.length === 0) {
        return;
    }

    const base = Math.min(0, ...highs);
    const range = Math.max(1, Math.max(...highs) - base);
    const bars = chart.querySelector('[data-chart-bars]');

    bars.replaceChildren(...days.map((day) => {
        const fragment = template.content.cloneNode(true);
        const height = Number.isFinite(day.high) ? 10 + 66 * (day.high - base) / range : 0;
        fragment.querySelector('[data-chart-value]').textContent = measurement(day.high, '°');
        fragment.querySelector('[data-chart-column]').style.height = height + 'px';
        fragment.querySelector('[data-chart-label]').textContent = day.weekday;
        return fragment;
    }));
    chart.hidden = false;
    chart.closest('[data-week-chart-panel]').hidden = false;
}

export function mountWeather(root) {
    const status = dataStatus(root);
    const current = root.querySelector('[data-weather-current]');
    const account = root.querySelector('[data-weather-account]');
    const chart = root.querySelector('[data-week-chart]');

    async function load({ fromRetry = false } = {}) {
        status.show('Cargando el pronóstico…', { restoreFocus: fromRetry });
        account.hidden = true;
        try {
            const forecast = await getJson(root.dataset.weather);
            const days = forecastDays(forecast.daily);

            if (current) {
                fillCurrent(current, forecast.current, days[0]);
                const date = root.querySelector('[data-current-date]');
                const time = date.querySelector('time');
                time.dateTime = days[0].isoDate;
                time.textContent = days[0].longDate;
                date.hidden = false;
            }
            fillDays(root, days);
            if (chart) {
                fillChart(chart, root.querySelector('[data-chart-bar]'), days);
                const range = root.querySelector('[data-week-range]');
                range.textContent = ' · ' + days[0].shortDate + ' – ' + days.at(-1).shortDate;
                range.hidden = false;
            }
            status.show('Pronóstico actualizado.', { visuallyHidden: true });
        } catch (error) {
            if (error.status === 422) {
                status.show('Aún no tienes una ubicación guardada para mostrar el pronóstico.');
                account.hidden = false;
            } else if (error.status === 401) {
                status.show('Tu sesión terminó. Vuelve a entrar para ver el pronóstico.', { sessionExpired: true });
            } else {
                status.show('No pudimos cargar el clima. Prueba de nuevo en unos minutos.', { canRetry: true });
            }
        }
    }

    status.retry.addEventListener('click', () => load({ fromRetry: true }));
    return load();
}
