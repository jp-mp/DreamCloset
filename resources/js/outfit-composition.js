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
    let colorPixels = 0;
    let red = 0;
    let green = 0;
    let blue = 0;
    const rows = new Uint16Array(sample.height);
    const columns = new Uint16Array(sample.width);

    for (let y = 0; y < sample.height; y++) {
        for (let x = 0; x < sample.width; x++) {
            const pixel = (y * sample.width + x) * 4;
            const alpha = data[pixel + 3];
            if (alpha <= 16) {
                transparent++;
            }
            if (alpha >= 64) {
                visible++;
                rows[y]++;
                columns[x]++;
            }
            if (alpha >= 192) {
                red += data[pixel];
                green += data[pixel + 1];
                blue += data[pixel + 2];
                colorPixels++;
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

    const color = colorPixels ? [red / colorPixels, green / colorPixels, blue / colorPixels] : null;

    return { garment, sample, left, top, width: right - left + 1, height: bottom - top + 1, color };
}

function backdropColor(cutouts) {
    const colors = cutouts.map(({ color }) => color).filter(Boolean);
    const temperature = colors.reduce((sum, [red, , blue]) => sum + red - blue, 0) / Math.max(colors.length, 1);
    const hasLightGarment = colors.some(([red, green, blue]) => 0.21 * red + 0.72 * green + 0.07 * blue > 200);

    if (temperature > 10) {
        return hasLightGarment ? '#c0ae98' : '#e7dac8';
    }
    if (temperature < -10) {
        return hasLightGarment ? '#aebac0' : '#dce2e4';
    }

    return hasLightGarment ? '#b7b2a9' : '#e2ded5';
}

function drawCutout(brush, cutout, slot) {
    const scale = Math.min(slot.width / cutout.width, slot.height / cutout.height);
    const drawnWidth = cutout.width * scale;
    const drawnHeight = cutout.height * scale;

    brush.drawImage(
        cutout.sample, cutout.left, cutout.top, cutout.width, cutout.height,
        slot.x + (slot.width - drawnWidth) / 2, slot.y + (slot.height - drawnHeight) / 2,
        drawnWidth, drawnHeight,
    );
}

function paintOutfit(canvas, cutouts) {
    canvas.width = width;
    canvas.height = height;
    const brush = canvas.getContext('2d');
    brush.fillStyle = backdropColor(cutouts);
    brush.fillRect(0, 0, width, height);
    brush.imageSmoothingQuality = 'high';
    brush.shadowColor = '#39291e40';
    brush.shadowBlur = 16;
    brush.shadowOffsetY = 9;

    if (cutouts.length === 1) {
        const slot = cutouts[0].garment.category === 'accessory'
            ? { x: 245, y: 320, width: 410, height: 485 }
            : { x: 125, y: 125, width: 650, height: 875 };
        drawCutout(brush, cutouts[0], slot);
        return;
    }

    const accessories = cutouts.filter(({ garment }) => garment.category === 'accessory');
    const garments = cutouts.filter(({ garment }) => garment.category !== 'accessory');
    const garmentHeightScale = accessories.length ? 900 / height : 1;
    const columns = garments.length <= 2
        ? garments.map((cutout) => [cutout])
        : [
            garments.filter(({ garment }) => ['top', 'bottom'].includes(garment.category)),
            garments.filter(({ garment }) => ['outerwear', 'footwear'].includes(garment.category)),
        ];

    const slots = {
        top: { y: 47, height: 380 },
        bottom: { y: 449, height: 625 },
        outerwear: { y: 47, height: 500 },
        footwear: { y: 580, height: 494 },
    };

    columns.forEach((column, index) => {
        const columnWidth = columns.length === 1 ? 580 : 387;
        const x = columns.length === 1 ? (width - columnWidth) / 2 : index === 0 ? 44 : 469;

        column.forEach((cutout) => {
            const slot = column.length === 1 ? { y: 180, height: 765 } : slots[cutout.garment.category];
            drawCutout(brush, cutout, {
                x,
                y: slot.y * garmentHeightScale,
                width: columnWidth,
                height: slot.height * garmentHeightScale,
            });
        });
    });

    const accessoryWidth = Math.min(290, width / Math.max(accessories.length, 1));
    accessories.forEach((accessory, index) => {
        drawCutout(brush, accessory, {
            x: (width - accessories.length * accessoryWidth) / 2 + index * accessoryWidth,
            y: garments.length ? 890 : 320,
            width: accessoryWidth,
            height: garments.length ? 190 : 485,
        });
    });
}

export function mountOutfitImage(root, day, cutoutCache) {
    const compose = root.querySelector('[data-compose-outfit]');
    const status = root.querySelector('[data-compose-status]');
    const problems = root.querySelector('[data-compose-problems]');
    const problemsList = problems.querySelector('[data-compose-links]');
    const figure = root.querySelector('[data-outfit-image]');
    const canvas = figure.querySelector('canvas');

    const download = root.querySelector('[data-download-outfit]');

    function showProblems(missingPhotos, hasComposition) {
        problemsList.replaceChildren(...missingPhotos.map(({ garment, action }) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.className = 'font-semibold underline underline-offset-4';
            link.href = garment.edit_url;
            link.textContent = action + ': ' + garment.name;
            item.append(link);
            return item;
        }));
        problems.open = !hasComposition;
        problems.hidden = false;
    }

    async function composeImage() {
        if (!root.isConnected || compose.disabled) {
            return;
        }

        compose.disabled = true;
        compose.hidden = true;
        problems.hidden = true;
        status.textContent = 'Preparando la imagen del outfit…';

        try {
            const cutouts = [];
            const missingPhotos = [];
            for (const garment of day.garments) {
                if (!garment.photo_url) {
                    missingPhotos.push({ garment, action: 'Agregar foto' });
                    continue;
                }

                if (!cutoutCache.has(garment.photo_url)) {
                    cutoutCache.set(garment.photo_url, loadCutout(garment).catch(() => null));
                }

                const cutout = await cutoutCache.get(garment.photo_url);
                if (!root.isConnected) {
                    return;
                }
                if (cutout) {
                    cutouts.push(cutout);
                } else {
                    missingPhotos.push({ garment, action: 'Revisar foto y quitar fondo' });
                }
            }

            if (missingPhotos.length) {
                showProblems(missingPhotos, cutouts.length > 0);
            }

            if (cutouts.length === 0) {
                status.textContent = 'Prepara al menos una foto sin fondo para ver la imagen del outfit.';
                return;
            }

            paintOutfit(canvas, cutouts);
            canvas.setAttribute('aria-label', 'Imagen del outfit: ' + cutouts.map(({ garment }) => garment.name).join(', '));
            figure.hidden = false;
            status.textContent = missingPhotos.length
                ? 'La imagen muestra ' + cutouts.length + ' de ' + day.garments.length + ' prendas.'
                : '';
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
    return composeImage;
}
