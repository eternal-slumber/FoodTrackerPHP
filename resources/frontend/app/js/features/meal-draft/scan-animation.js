// Calm particle animation for the meal photo scanning state

const scanOverlay = document.querySelector('.scan-overlay');
const scanParticles = scanOverlay?.querySelector('.scan-particles');
const scanMotionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
const SCAN_PARTICLE_ANCHORS = [
    { x: -62, y: -30, driftX: -10, driftY: -5, size: 5, tone: '' },
    { x: -35, y: -47, driftX: -6, driftY: -8, size: 4, tone: 'is-lime' },
    { x: 5, y: -48, driftX: 1, driftY: -7, size: 6, tone: 'is-light' },
    { x: 41, y: -39, driftX: 7, driftY: -7, size: 5, tone: '' },
    { x: 64, y: -12, driftX: 10, driftY: -2, size: 4, tone: 'is-lime' },
    { x: 61, y: 22, driftX: 10, driftY: 4, size: 5, tone: 'is-light' },
    { x: 33, y: 43, driftX: 6, driftY: 8, size: 4, tone: '' },
    { x: -5, y: 41, driftX: -1, driftY: 7, size: 5, tone: 'is-lime' },
    { x: -43, y: 39, driftX: -8, driftY: 7, size: 6, tone: 'is-light' },
    { x: -66, y: 7, driftX: -10, driftY: 1, size: 4, tone: '' }
];
const scanParticleElements = createScanParticleElements();
let scanParticleTimer = null;
let lastScanParticleIndex = -1;

function setPhotoScanAnimation(isScanning) {
    draftPhotoPreview?.classList.toggle('is-scanning', isScanning);
    scanOverlay?.setAttribute('aria-hidden', String(!isScanning));

    if (isScanning && !scanMotionPreference.matches) {
        scheduleScanParticle();
        return;
    }

    stopScanParticles();
}

function scheduleScanParticle() {
    if (scanParticleTimer !== null || !draftPhotoPreview?.classList.contains('is-scanning')) {
        return;
    }

    const delay = randomScanValue(250, 450);
    scanParticleTimer = window.setTimeout(() => {
        scanParticleTimer = null;
        createScanParticle();
        scheduleScanParticle();
    }, delay);
}

function createScanParticle() {
    if (!scanParticles || scanMotionPreference.matches) {
        return;
    }

    const availableParticles = scanParticleElements.filter((particle, index) => (
        !particle.classList.contains('is-active') && index !== lastScanParticleIndex
    ));
    const candidates = availableParticles.length > 0
        ? availableParticles
        : scanParticleElements.filter(particle => !particle.classList.contains('is-active'));

    if (candidates.length === 0) {
        return;
    }

    const particle = candidates[randomScanValue(0, candidates.length - 1)];
    lastScanParticleIndex = Number(particle.dataset.scanParticleIndex);
    particle.style.setProperty('--particle-life', `${randomScanValue(900, 1300)}ms`);
    particle.classList.add('is-active');
}

function stopScanParticles() {
    if (scanParticleTimer !== null) {
        window.clearTimeout(scanParticleTimer);
        scanParticleTimer = null;
    }

    scanParticleElements.forEach(particle => particle.classList.remove('is-active'));
    lastScanParticleIndex = -1;
}

function createScanParticleElements() {
    if (!scanParticles) {
        return [];
    }

    const particles = SCAN_PARTICLE_ANCHORS.map((anchor, index) => {
        const particle = document.createElement('span');

        particle.className = `scan-particle ${anchor.tone}`.trim();
        particle.dataset.scanParticleIndex = String(index);
        particle.style.setProperty('--particle-x', `${anchor.x}px`);
        particle.style.setProperty('--particle-y', `${anchor.y}px`);
        particle.style.setProperty('--particle-drift-x', `${anchor.driftX}px`);
        particle.style.setProperty('--particle-drift-y', `${anchor.driftY}px`);
        particle.style.setProperty('--particle-size', `${anchor.size}px`);
        particle.addEventListener('animationend', () => particle.classList.remove('is-active'));

        return particle;
    });

    scanParticles.replaceChildren(...particles);
    return particles;
}

function randomScanValue(min, max) {
    return Math.round(min + Math.random() * (max - min));
}

scanMotionPreference.addEventListener?.('change', () => {
    setPhotoScanAnimation(Boolean(isAnalyzingPhoto));
});
