import { areaCenter, detailMap, divisionsOf, globeMap, mapAreas } from './location-map-geometry.js';

const localLevels = { CHL: 3, ESP: 3 };
const sorted = (areas) => [...areas].sort((first, second) => first.name.localeCompare(second.name, 'es'));

async function requestJson(url) {
    const response = await fetch(url, { signal: AbortSignal.timeout(20000) });
    if (!response.ok) {
        throw new Error('No se pudo cargar el mapa.');
    }
    return response.json();
}

async function remoteBoundaries(country, level) {
    const metadata = await requestJson(`https://www.geoboundaries.org/api/current/gbOpen/${country}/ADM${level}/`);
    const download = new URL(metadata.simplifiedGeometryGeoJSON);
    if (download.origin === 'https://github.com' && download.pathname.startsWith('/wmgeolab/geoBoundaries/raw/')) {
        download.href = 'https://media.githubusercontent.com/media' + download.pathname.replace('/raw/', '/');
    }
    return requestJson(download);
}

export function mountLocationMap(form) {
    const root = form.querySelector('[data-location-map]');
    if (!root) {
        return;
    }

    const canvas = root.querySelector('[data-map-canvas]');
    if (!canvas.getContext('2d')) {
        return;
    }

    const { name, latitude, longitude } = form.elements;
    const back = root.querySelector('[data-map-back]');
    const context = root.querySelector('[data-map-context]');
    const rotate = root.querySelector('[data-map-rotate]');
    const label = root.querySelector('[data-map-label]');
    const select = root.querySelector('[data-map-select]');
    const status = root.querySelector('[data-map-status]');
    const tooltip = root.querySelector('[data-map-tooltip]');
    const retry = root.querySelector('[data-map-retry]');
    const chileCredit = root.querySelector('[data-map-chile-credit]');
    const geolocationStatus = form.querySelector('[data-geolocation-status]');
    const saveStatus = form.querySelector('[data-save-status]');
    const assets = root.dataset.mapAssets;
    const cache = new Map();

    let world = [];
    let globe;
    let regions = [];
    let country;
    let region;
    let selected;
    let hovered;
    let visibleAreas = [];
    let renderer;
    let choose;
    let level = 0;
    let revision = 0;
    let paintFrame;
    let gesture;

    function redraw() {
        if (paintFrame !== undefined) {
            return;
        }
        paintFrame = requestAnimationFrame(() => {
            paintFrame = undefined;
            renderer?.draw(hovered, selected);
        });
    }

    function showAreas(areas, action) {
        visibleAreas = areas;
        choose = action;
        select.replaceChildren(new Option(areas.length ? label.textContent : 'Sin opciones disponibles', ''));
        for (const area of sorted(areas)) {
            select.add(new Option(area.name, area.id));
        }
        select.disabled = !action;
    }

    function showLevel(nextLevel, caption, title, message) {
        level = nextLevel;
        hovered = undefined;
        selected = undefined;
        tooltip.hidden = true;
        retry.hidden = true;
        back.hidden = level === 0;
        rotate.hidden = level !== 0 || !globe;
        canvas.toggleAttribute('data-globe', level === 0 && Boolean(globe));
        chileCredit.hidden = level === 0 || country?.id !== 'CHL';
        context.textContent = caption;
        label.textContent = title;
        status.textContent = message;
        showAreas([]);
    }

    async function boundaries(level) {
        const code = country.id;
        const key = code + level;
        if (!cache.has(key)) {
            const file = level === 1 ? 'chile-regions.json' : 'chile-communes.json';
            const collection = code === 'CHL'
                ? await requestJson(assets + '/' + file)
                : await remoteBoundaries(code, level);
            const areas = mapAreas(collection);
            cache.set(key, areas);
        }
        return cache.get(key);
    }

    function showWorld(focus = false) {
        revision++;
        country = undefined;
        region = undefined;
        renderer = globe;
        showLevel(0, 'Mundo', 'Selecciona un país', 'Arrastra el globo para girarlo y selecciona un país.');
        showAreas(world, globe ? openCountry : undefined);
        if (focus) {
            select.focus();
        }
        redraw();
    }

    async function loadWorld() {
        showLevel(0, 'Mundo', 'Selecciona un país', 'Cargando el mapa mundial…');
        try {
            world = mapAreas(await requestJson(assets + '/world.json'));
            globe = globeMap(canvas, world);
            showWorld();
        } catch {
            status.textContent = 'No pudimos cargar el mapa. Puedes reintentar o ingresar las coordenadas abajo.';
            retry.onclick = loadWorld;
            retry.hidden = false;
        }
    }

    function showRegions(focus = false) {
        revision++;
        region = undefined;
        showLevel(1, country.name, 'Selecciona una región', 'Elige una región o su equivalente en el mapa o en la lista.');
        renderer = detailMap(canvas, regions, country);
        showAreas(regions, openRegion);
        if (focus) {
            select.focus();
        }
        redraw();
    }

    function showLoadError(message, reload) {
        status.textContent = message + ' Puedes reintentar o ingresar las coordenadas abajo.';
        retry.onclick = reload;
        retry.hidden = false;
    }

    async function openCountry(area, focus = false) {
        const request = ++revision;
        country = area;
        showLevel(1, country.name, 'Selecciona una región', 'Cargando regiones…');
        renderer = detailMap(canvas, [country], country);
        redraw();

        try {
            const loadedRegions = await boundaries(1);
            if (request !== revision) {
                return;
            }
            if (loadedRegions.length === 0) {
                throw new Error('Sin regiones.');
            }
            regions = loadedRegions;
            showRegions(focus);
        } catch {
            if (request === revision) {
                showLoadError('Las regiones de este país no están disponibles.', () => openCountry(area, true));
            }
        }
    }

    async function openRegion(area, focus = false) {
        const request = ++revision;
        region = area;
        const isChile = country.id === 'CHL';
        const title = isChile ? 'Selecciona una comuna' : 'Selecciona una división local';
        const nextLevel = localLevels[country.id] ?? 2;
        showLevel(2, region.name, title, isChile ? 'Cargando comunas…' : 'Cargando divisiones locales…');
        renderer = detailMap(canvas, [region], region);
        redraw();

        try {
            const localAreas = await boundaries(nextLevel);
            if (request !== revision) {
                return;
            }
            const divisions = divisionsOf(area, localAreas);
            if (divisions.length === 0) {
                throw new Error('Sin divisiones.');
            }
            renderer = detailMap(canvas, divisions, region);
            showAreas(divisions, selectDivision);
            status.textContent = isChile ? 'Elige una comuna en el mapa o en la lista.' : 'Elige un municipio, distrito o división equivalente.';
            if (focus) {
                select.focus();
            }
            redraw();
        } catch {
            if (request === revision) {
                showLoadError('Las divisiones de esta región no están disponibles.', () => openRegion(area, true));
            }
        }
    }

    function selectDivision(area) {
        const [lng, lat] = areaCenter(area);
        selected = area;
        select.value = area.id;
        name.value = [area.name, region.name, country.name].filter((part, index, parts) => parts.indexOf(part) === index)
            .join(', ').slice(0, name.maxLength);
        latitude.value = lat.toFixed(5);
        longitude.value = lng.toFixed(5);
        geolocationStatus.textContent = '';
        saveStatus.textContent = '';
        status.textContent = area.name + ' seleccionada. Pulsa Guardar ubicación para aplicar el cambio.';
        redraw();
    }

    function pointedArea(event) {
        const rect = canvas.getBoundingClientRect();
        return renderer?.hit((event.clientX - rect.left) * canvas.width / rect.width,
            (event.clientY - rect.top) * canvas.height / rect.height);
    }

    function showHover(area) {
        if (hovered === area) {
            return;
        }
        hovered = area;
        tooltip.hidden = !area || !choose;
        tooltip.textContent = area?.name ?? '';
        redraw();
    }

    select.addEventListener('change', () => {
        const area = visibleAreas.find((item) => item.id === select.value);
        if (area) {
            choose?.(area, true);
        }
    });
    back.addEventListener('click', () => {
        if (level === 2) {
            showRegions(true);
        } else {
            showWorld(true);
        }
    });

    rotate.addEventListener('click', (event) => {
        const turn = Number(event.target.closest('button')?.dataset.mapTurn);
        if (turn) {
            globe.turn(turn * 35, 0);
            showHover(undefined);
            redraw();
        }
    });

    canvas.addEventListener('pointerdown', (event) => {
        if (!event.isPrimary || event.button !== 0) {
            return;
        }
        gesture = { pointer: event.pointerId, x: event.clientX, y: event.clientY, startX: event.clientX, startY: event.clientY, moved: false };
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', (event) => {
        if (gesture?.pointer !== event.pointerId) {
            showHover(pointedArea(event));
            return;
        }
        if (!gesture.moved && Math.hypot(event.clientX - gesture.startX, event.clientY - gesture.startY) < 5) {
            return;
        }
        gesture.moved = true;
        if (level === 0 && globe) {
            const speed = 245 / canvas.clientWidth;
            globe.turn((gesture.x - event.clientX) * speed, (event.clientY - gesture.y) * speed);
            showHover(undefined);
            redraw();
        }
        gesture.x = event.clientX;
        gesture.y = event.clientY;
    });
    canvas.addEventListener('pointerup', (event) => {
        if (gesture?.pointer !== event.pointerId) {
            return;
        }
        if (!gesture.moved) {
            const area = pointedArea(event);
            if (area) {
                choose?.(area);
            }
        }
        gesture = undefined;
    });
    canvas.addEventListener('lostpointercapture', () => { gesture = undefined; });
    canvas.addEventListener('pointerleave', () => showHover(undefined));

    form.addEventListener('location:saved', ({ detail }) => {
        if (selected && latitude.value === Number(detail.latitude).toFixed(5)
            && longitude.value === Number(detail.longitude).toFixed(5)) {
            status.textContent = selected.name + ' · Ubicación guardada.';
        }
    });

    for (const input of [latitude, longitude]) {
        input.addEventListener('input', () => {
            if (selected) {
                selected = undefined;
                select.value = '';
                status.textContent = 'Se usará la ubicación indicada debajo del mapa.';
                redraw();
            }
        });
    }

    root.hidden = false;
    loadWorld();
}
