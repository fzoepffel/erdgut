import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';

import './hero-parallax';
import './reveal-observer';

window.Alpine = Alpine;

Alpine.plugin(intersect);
Alpine.start();
