/**
 * Deferred hero video.
 *
 * Hero <video> elements ship without a <source>; the file URL sits in
 * `data-video-src` (optional `data-video-type`, defaults to video/mp4).
 * The source is only attached on viewports from 640px up and when the
 * visitor does not prefer reduced motion. Phones and no-JS visitors get
 * the still <img> that sits underneath the video instead of a
 * multi-megabyte download.
 */

function initHeroVideos() {
    const videos = document.querySelectorAll('video[data-video-src]');
    if (videos.length === 0) return;

    if (!window.matchMedia('(min-width: 640px)').matches) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    videos.forEach((video) => {
        const source = document.createElement('source');
        source.src = video.dataset.videoSrc;
        source.type = video.dataset.videoType || 'video/mp4';
        video.appendChild(source);
        video.load();

        const playing = video.play();
        if (playing && typeof playing.catch === 'function') {
            playing.catch(() => {
                // Autoplay refused (e.g. battery-saving mode): drop the
                // video so the still image underneath shows, instead of
                // the browser's native play overlay.
                video.style.display = 'none';
            });
        }
    });
}

if (typeof window !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHeroVideos);
    } else {
        initHeroVideos();
    }
}
