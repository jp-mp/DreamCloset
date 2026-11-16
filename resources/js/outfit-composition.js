const width = 900;
const height = 1125;
const sampleSize = 540;

async function loadCutout(garment) {
    const image = new Image();
    image.src = garment.photo_url;
    await image.decode();

    const scale = Math.min(1, sampleSize / Math.max(image.naturalWidth, image.naturalHeight));
    const sample = document.createElement('canvas');
    sample.width = Math.max(1, Math.round(image.naturalWidth * scale));
    sample.height = Math.max(1, Math.round(image.naturalHeight * scale));

    const brush = sample.getContext('2d', { willReadFrequently: true });
    brush.drawImage(image, 0, 0, sample.width, sample.height);
    const { data } = brush.getImageData(0, 0, sample.width, sample.height);

    let transparent = 0;
    let visible = 0;
    const rows = new Uint16Array(sample.height);
    const columns = new Uint16Array(sample.width);

    for (let y = 0; y < sample.height; y++) {
        for (let x = 0; x < sample.width; x++) {
            const alpha = data[(y * sample.width + x) * 4 + 3];
            if (alpha <= 16) {
                transparent++;
            }
            if (alpha >= 64) {
                visible++;
                rows[y]++;
                columns[x]++;
            }
        }
    }

    const pixels = sample.width * sample.height;
    if (transparent < pixels * 0.02 || visible < pixels * 0.02) {
        return null;
    }

    const rowThreshold = Math.max(1, Math.ceil(sample.width / 140));
    const columnThreshold = Math.max(1, Math.ceil(sample.height / 140));
    const left = Math.max(0, columns.findIndex((count) => count >= columnThreshold) - 2);
    const right = Math.min(sample.width - 1, columns.findLastIndex((count) => count >= columnThreshold) + 2);
    const top = Math.max(0, rows.findIndex((count) => count >= rowThreshold) - 2);
    const bottom = Math.min(sample.height - 1, rows.findLastIndex((count) => count >= rowThreshold) + 2);

    return { garment, sample, left, top, width: right - left + 1, height: bottom - top + 1 };
}

function paintBackdrop(brush) {
    const tile = document.createElement('canvas');
    tile.width = 64;
    tile.height = 64;
    const texture = tile.getContext('2d');
    texture.fillStyle = '#e7dac8';
    texture.fillRect(0, 0, tile.width, tile.height);

    for (let line = 0; line < tile.width; line += 2) {
        texture.fillStyle = line % 8 === 0 ? '#87725612' : '#ffffff35';
        texture.fillRect(line, 0, 1, tile.height);
        texture.fillStyle = line % 6 === 0 ? '#87725615' : '#ffffff26';
        texture.fillRect(0, line, tile.width, 1);
    }

    brush.fillStyle = brush.createPattern(tile, 'repeat');
    brush.fillRect(0, 0, width, height);

    const light = brush.createRadialGradient(300, 230, 30, 460, 500, 760);
    light.addColorStop(0, '#ffffff38');
    light.addColorStop(1, '#795d3f20');
    brush.fillStyle = light;
    brush.fillRect(0, 0, width, height);
}

function paintOutfit(canvas, cutouts) {
    canvas.width = width;
    canvas.height = height;
    const brush = canvas.getContext('2d');
    paintBackdrop(brush);
    brush.imageSmoothingQuality = 'high';
    brush.shadowColor = '#39291e40';
    brush.shadowBlur = 16;
    brush.shadowOffsetY = 9;

    const columns = cutouts.length === 2
        ? cutouts.map((cutout) => [cutout])
        : [
            cutouts.filter(({ garment }) => ['top', 'bottom'].includes(garment.category)),
            cutouts.filter(({ garment }) => ['outerwear', 'footwear'].includes(garment.category)),
        ];

    columns.forEach((column, index) => {
        const x = index === 0 ? 44 : 469;
        const columnWidth = 387;

        column.forEach((cutout) => {
            const slot = column.length === 1
                ? { y: 180, height: 765 }
                : {
                    top: { y: 47, height: 380 },
                    bottom: { y: 449, height: 625 },
                    outerwear: { y: 47, height: 500 },
                    footwear: { y: 580, height: 494 },
                }[cutout.garment.category];
            const scale = Math.min(columnWidth / cutout.width, slot.height / cutout.height);
            const drawnWidth = cutout.width * scale;
            const drawnHeight = cutout.height * scale;

            brush.drawImage(
                cutout.sample, cutout.left, cutout.top, cutout.width, cutout.height,
                x + (columnWidth - drawnWidth) / 2, slot.y + (slot.height - drawnHeight) / 2, drawnWidth, drawnHeight,
            );
        });
    });
}

export function mountOutfitImage(root, day, automatic) {
    const compose = root.querySelector('[data-compose-outfit]');
    const status = root.querySelector('[data-compose-status]');
    const problemsList = root.querySelector('[data-compose-problems]');
    const figure = root.querySelector('[data-outfit-image]');
    const canvas = figure.querySelector('canvas');

    const download = root.querySelector('[data-download-outfit]');

    function showProblems(problems) {
        status.textContent = 'Prepara estas fotos para incluir todas las prendas en la imagen.';
        problemsList.replaceChildren(...problems.map(({ garment, action }) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.className = 'font-semibold underline underline-offset-4';
            link.href = garment.edit_url;
            link.textContent = action + ': ' + garment.name;
            item.append(link);
            return item;
        }));
        problemsList.hidden = false;
        compose.hidden = true;
    }

    async function composeImage() {
        compose.disabled = true;
        problemsList.hidden = true;
        status.textContent = 'Preparando la imagen del outfit…';

        try {
            const cutouts = [];
            const problems = [];
            for (const garment of day.garments) {
                if (!garment.photo_url) {
                    problems.push({ garment, action: 'Agregar foto' });
                    continue;
                }

                const cutout = await loadCutout(garment).catch(() => null);
                if (!root.isConnected) {
                    return;
                }
                if (cutout) {
                    cutouts.push(cutout);
                } else {
                    problems.push({ garment, action: 'Revisar foto y quitar fondo' });
                }
            }

            if (problems.length) {
                showProblems(problems);
                return;
            }

            if (cutouts.length < 2) {
                status.textContent = 'Agrega otra prenda al clóset para formar un outfit con imagen.';
                return;
            }

            paintOutfit(canvas, cutouts);
            canvas.setAttribute('aria-label', 'Outfit sobre fondo de lino: ' + cutouts.map(({ garment }) => garment.name).join(', '));
            figure.hidden = false;
            compose.hidden = true;
            root.parentElement.dataset.composed = 'true';
            if (!automatic) {
                download.focus({ preventScroll: true });
            }
            status.textContent = '';
        } catch {
            status.textContent = 'No pudimos crear la imagen. Inténtalo de nuevo.';
            compose.hidden = false;
        } finally {
            compose.disabled = false;
        }
    }

    download.addEventListener('click', () => {
        canvas.toBlob((blob) => {
            if (!blob) {
                status.textContent = 'No pudimos descargar la imagen.';
                return;
            }
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'dreamcloset-' + day.date + '.png';
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }, 'image/png');
    });

    compose.addEventListener('click', composeImage);
    if (automatic) {
        composeImage();
    } else {
        compose.hidden = day.garments.length === 0;
    }
}
