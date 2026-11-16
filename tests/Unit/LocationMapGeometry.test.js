import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { areaCenter, divisionsOf, mapAreas } from '../../resources/js/location-map-geometry.js';

function loadMap(name) {
    return mapAreas(JSON.parse(readFileSync(new URL(`../../public/images/maps/${name}.json`, import.meta.url))));
}

test('lists the communes of Metropolitana and returns the saved map point for Santiago', () => {
    const regions = loadMap('chile-regions');
    const communes = loadMap('chile-communes');
    const metropolitana = regions.find((region) => region.name === 'Metropolitana de Santiago');

    const local = divisionsOf(metropolitana, communes);

    assert.equal(local.length, 52);
    assert.ok(!local.some((area) => area.name === 'Valparaíso'));
    assert.deepEqual(areaCenter(local.find((area) => area.name === 'Santiago')), [-70.65339, -33.46045]);
});

test('assigns divisions without parent IDs by polygon containment, excluding holes', () => {
    const square = (min, max) => [[min, min], [max, min], [max, max], [min, max], [min, min]];
    const region = { polygons: [[square(0, 10), square(3, 7)]] };
    const local = { polygons: [[square(1, 2)]] };
    const inHole = { polygons: [[square(4, 6)]] };
    const elsewhere = { polygons: [[square(11, 12)]] };

    const divisions = divisionsOf(region, [local, inHole, elsewhere]);

    assert.deepEqual(divisions, [local]);
    assert.deepEqual(areaCenter(local), [1.5, 1 + 1 / 12]);
});
