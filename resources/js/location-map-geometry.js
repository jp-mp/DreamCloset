const radians = Math.PI / 180;
const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));

export function mapAreas(collection) {
    return collection.features.flatMap(({ geometry, properties }) => {
        if (!['Polygon', 'MultiPolygon'].includes(geometry?.type)) {
            return [];
        }

        const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
        const name = properties.name ?? properties.shapeName;
        return name ? [{
            id: properties.id ?? properties.shapeID,
            name,
            region: properties.region,
            center: properties.center,
            polygons,
        }] : [];
    });
}

function polygonBounds(polygons, project = (point) => point) {
    const bounds = [Infinity, Infinity, -Infinity, -Infinity];
    for (const [outline] of polygons) {
        for (const point of outline) {
            const [x, y] = project(point);
            bounds[0] = Math.min(bounds[0], x);
            bounds[1] = Math.min(bounds[1], y);
            bounds[2] = Math.max(bounds[2], x);
            bounds[3] = Math.max(bounds[3], y);
        }
    }
    return bounds;
}

function ringCrossings(ring, latitude) {
    const crossings = [];
    for (let index = 1; index < ring.length; index++) {
        const [ax, ay] = ring[index - 1];
        const [bx, by] = ring[index];
        if ((ay > latitude) !== (by > latitude)) {
            crossings.push(ax + (latitude - ay) * (bx - ax) / (by - ay));
        }
    }
    return crossings;
}

function areaContains(area, [longitude, latitude]) {
    area.bounds ??= polygonBounds(area.polygons);
    const [left, bottom, right, top] = area.bounds;
    return longitude >= left && longitude <= right && latitude >= bottom && latitude <= top
        && area.polygons.some((polygon) => polygon.flatMap((ring) => ringCrossings(ring, latitude))
            .filter((crossing) => crossing > longitude).length % 2 === 1);
}

function ringArea(ring) {
    let area = 0;
    for (let index = 1; index < ring.length; index++) {
        const [ax, ay] = ring[index - 1];
        const [bx, by] = ring[index];
        area += ax * by - bx * ay;
    }
    return Math.abs(area);
}

export function areaCenter(area) {
    if (area.center) {
        return area.center;
    }

    let largest = area.polygons[0];
    let largestArea = ringArea(largest[0]);
    for (const polygon of area.polygons.slice(1)) {
        const size = ringArea(polygon[0]);
        if (size > largestArea) {
            largest = polygon;
            largestArea = size;
        }
    }

    const [, bottom, , top] = polygonBounds([largest]);
    let bestWidth = 0;
    for (let position = 1; position < 12; position++) {
        const latitude = bottom + (top - bottom) * position / 12;
        const crossings = largest.flatMap((ring) => ringCrossings(ring, latitude)).sort((a, b) => a - b);
        for (let index = 0; index + 1 < crossings.length; index += 2) {
            const width = crossings[index + 1] - crossings[index];
            if (width > bestWidth) {
                bestWidth = width;
                area.center = [crossings[index] + width / 2, latitude];
            }
        }
    }
    return area.center ?? largest[0][0];
}

export function divisionsOf(region, divisions) {
    return divisions.filter((area) => area.region
        ? area.region === region.id
        : areaContains(region, areaCenter(area)));
}

function trace(path, polygons, project) {
    for (const polygon of polygons) {
        for (const ring of polygon) {
            path.moveTo(...project(ring[0]));
            for (let index = 1; index < ring.length; index++) {
                path.lineTo(...project(ring[index]));
            }
            path.closePath();
        }
    }
}

export function detailMap(canvas, areas, parent) {
    const { width, height } = canvas;
    const context = canvas.getContext('2d');
    const [longitude, latitude] = areaCenter(parent);
    const proportion = Math.max(0.15, Math.cos(latitude * radians));
    const plane = ([x, y]) => [(((x - longitude + 540) % 360) - 180) * proportion, -y];
    const areaBounds = areas.map((area) => polygonBounds(area.polygons, plane));
    const left = Math.min(...areaBounds.map((bounds) => bounds[0]));
    const top = Math.min(...areaBounds.map((bounds) => bounds[1]));
    const right = Math.max(...areaBounds.map((bounds) => bounds[2]));
    const bottom = Math.max(...areaBounds.map((bounds) => bounds[3]));
    const scale = Math.min((width - 62) / Math.max(right - left, 0.01), (height - 68) / Math.max(bottom - top, 0.01));
    const scalePoint = ([x, y]) => [width / 2 + (x - (left + right) / 2) * scale, height / 2 + (y - (top + bottom) / 2) * scale];
    const project = (point) => scalePoint(plane(point));
    const shapes = areas.map((area, index) => {
        const path = new Path2D();
        trace(path, area.polygons, project);
        const bounds = areaBounds[index];
        return { area, path, minimum: scalePoint(bounds.slice(0, 2)), maximum: scalePoint(bounds.slice(2)) };
    });

    return {
        draw(hovered, selected) {
            context.clearRect(0, 0, width, height);
            for (const { area, path } of shapes) {
                context.fillStyle = area === selected ? '#e2a98d' : area === hovered ? '#b9d1c2' : '#709394';
                context.strokeStyle = '#253b44';
                context.lineWidth = 1;
                context.fill(path, 'evenodd');
                context.stroke(path);
            }
        },
        hit(x, y) {
            return shapes.find(({ minimum, maximum, path }) => x >= minimum[0] && x <= maximum[0]
                && y >= minimum[1] && y <= maximum[1] && context.isPointInPath(path, x, y, 'evenodd'))?.area;
        },
    };
}

function countryAtlas(areas) {
    const atlas = document.createElement('canvas');
    atlas.width = 1440;
    atlas.height = 720;
    const context = atlas.getContext('2d', { willReadFrequently: true });
    const project = ([longitude, latitude]) => [(longitude + 180) * 4, (90 - latitude) * 4];
    const codes = new Map();
    areas.forEach((area, index) => {
        const code = ((index + 1) * 0x9e3779) & 0xffffff;
        codes.set(code, index + 1);
        context.fillStyle = '#' + code.toString(16).padStart(6, '0');
        context.beginPath();
        trace(context, area.polygons, project);
        context.fill('evenodd');
    });

    const pixels = context.getImageData(0, 0, atlas.width, atlas.height).data;
    const countries = new Uint16Array(pixels.length / 4);
    for (let index = 0; index < countries.length; index++) {
        const offset = index * 4;
        if (pixels[offset + 3] >= 160) {
            const code = (pixels[offset] << 16) | (pixels[offset + 1] << 8) | pixels[offset + 2];
            countries[index] = codes.get(code) ?? 0;
        }
    }
    return { countries, width: atlas.width, height: atlas.height };
}

export function globeMap(canvas, areas) {
    const { width, height } = canvas;
    const context = canvas.getContext('2d');
    const atlas = countryAtlas(areas);
    const frame = context.createImageData(width, height);
    const radius = Math.floor(height * 0.415);
    const globeLeft = width / 2 - radius;
    const globeTop = height / 2 - radius;
    const diameter = radius * 2;
    const depth = new Float32Array(diameter * diameter);
    for (let row = 0; row < diameter; row++) {
        for (let column = 0; column < diameter; column++) {
            const x = (column + 0.5 - radius) / radius;
            const y = (row + 0.5 - radius) / radius;
            depth[row * diameter + column] = Math.sqrt(1 - x * x - y * y);
        }
    }

    let longitude = -72;
    let latitude = -21;

    function atlasIndex(x, y, z, sinLatitude, cosLatitude) {
        const lat = Math.asin(clamp(y * cosLatitude + z * sinLatitude, -1, 1)) / radians;
        const lon = Math.atan2(x, z * cosLatitude - y * sinLatitude) / radians + longitude;
        const column = ((lon + 540) % 360) * 4 | 0;
        const row = Math.min(atlas.height - 1, (90 - lat) * 4 | 0);
        return row * atlas.width + column;
    }

    return {
        turn(x, y) {
            longitude = ((longitude + x + 540) % 360) - 180;
            latitude = clamp(latitude + y, -88, 88);
        },
        draw(hovered) {
            const sinLatitude = Math.sin(latitude * radians);
            const cosLatitude = Math.cos(latitude * radians);
            const selected = hovered ? areas.indexOf(hovered) + 1 : -1;
            for (let row = 0; row < diameter; row++) {
                const y = (radius - row - 0.5) / radius;
                for (let column = 0; column < diameter; column++) {
                    const z = depth[row * diameter + column];
                    if (!Number.isFinite(z)) {
                        continue;
                    }

                    const x = (column + 0.5 - radius) / radius;
                    const index = atlasIndex(x, y, z, sinLatitude, cosLatitude);
                    const country = atlas.countries[index];
                    const shade = Math.max(0.45, 0.76 + 0.26 * z - 0.1 * x + 0.1 * y);
                    const edge = country && (atlas.countries[index + 1] !== country || atlas.countries[index + atlas.width] !== country);
                    const grid = index % atlas.width % 120 < 2 || (index / atlas.width | 0) % 120 < 2;
                    const color = country === selected ? [226, 169, 141] : country
                        ? edge ? [92, 124, 125] : [121, 151, 144]
                        : grid ? [52, 78, 88] : [37, 67, 80];
                    const pixel = ((row + globeTop) * width + column + globeLeft) * 4;
                    frame.data[pixel] = color[0] * shade;
                    frame.data[pixel + 1] = color[1] * shade;
                    frame.data[pixel + 2] = color[2] * shade;
                    frame.data[pixel + 3] = 255;
                }
            }
            context.putImageData(frame, 0, 0);
        },
        hit(x, y) {
            const dx = (x - width / 2) / radius;
            const dy = (height / 2 - y) / radius;
            const z = Math.sqrt(1 - dx * dx - dy * dy);
            if (!Number.isFinite(z)) {
                return;
            }

            const index = atlasIndex(dx, dy, z, Math.sin(latitude * radians), Math.cos(latitude * radians));
            return areas[atlas.countries[index] - 1];
        },
    };
}
