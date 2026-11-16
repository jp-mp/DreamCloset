import { mountCloset } from './closet.js';
import { mountDailyDrink } from './daily-drink.js';
import { mountGarmentForm, mountLocationForm } from './edit-forms.js';
import { mountRecommendations } from './outfit-recommendations.js';
import { mountWeather } from './weather.js';

const locationForm = document.querySelector('[data-location-form]');
if (locationForm) {
    mountLocationForm(locationForm);
}

const garmentForm = document.querySelector('[data-garment-form]');
if (garmentForm) {
    mountGarmentForm(garmentForm);
}

const weather = document.querySelector('[data-weather]');
const weatherReady = weather ? mountWeather(weather) : Promise.resolve();

const closet = document.querySelector('[data-closet]');
if (closet) {
    mountCloset(closet);
}

const recommendations = document.querySelector('[data-outfits]');
if (recommendations) {
    mountRecommendations(recommendations, weatherReady);
}

const dailyDrink = document.querySelector('[data-daily-drink]');
if (dailyDrink) {
    mountDailyDrink(dailyDrink);
}

const mobileMenu = document.querySelector('[data-mobile-menu]');

if (mobileMenu) {
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileMenu.open) {
            mobileMenu.open = false;
            mobileMenu.querySelector('summary').focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (mobileMenu.open && !mobileMenu.contains(event.target)) {
            mobileMenu.open = false;
        }
    });
}
