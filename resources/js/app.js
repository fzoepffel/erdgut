import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';

import './hero-parallax';
import './hero-video';
import './reveal-observer';

window.Alpine = Alpine;

Alpine.plugin(intersect);
Alpine.start();
