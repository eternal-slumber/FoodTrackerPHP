const steps = [...document.querySelectorAll('.story-step')];
const counter = document.querySelector('#current-step');
const phoneMotion = document.querySelector('.phone-motion');
const demoSticky = document.querySelector('.demo-sticky');
const productStory = document.querySelector('.product-story');
const mobileStoryStart = document.querySelector('.mobile-story-start');
const sceneLabels = ['telegram', 'openApp', 'addPhoto', 'analysis', 'fillFields', 'saveResult'];
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const numberFormat = new Intl.NumberFormat('ru-RU');
const heroReveal = [...document.querySelectorAll('.hero-reveal')];
const headerActions = [...document.querySelectorAll('.header-action')];
const pageRoot = document.documentElement;

if ('scrollRestoration' in window.history) window.history.scrollRestoration = 'manual';
pageRoot.style.scrollBehavior = 'auto';
window.scrollTo(0, 0);

let activeIndex = -1;
let committedIndex = -1;
let scrollFrame;
let snapFrame;
let snapTimeout;
let sceneTween;
let pageIsRestoring = true;
let demoUnlocked = false;
let demoUnlockTimer;

productStory.classList.toggle('is-demo-locked', window.innerWidth <= 768);

function unlockDemo() {
    if (demoUnlocked) return;
    demoUnlocked = true;
    productStory.classList.remove('is-demo-locked');
}

function lockDemo() {
    clearTimeout(demoUnlockTimer);
    demoUnlockTimer = null;
    demoUnlocked = false;
    productStory.classList.add('is-demo-locked');
}

function getMobileActivationLine() {
    const stickyTop = Number.parseFloat(window.getComputedStyle(demoSticky).top);
    return stickyTop - Math.min(228, window.innerHeight * .3);
}

function scheduleDemoUnlock() {
    if (demoUnlocked || demoUnlockTimer) return;
    demoUnlockTimer = window.setTimeout(() => {
        demoUnlockTimer = null;
        unlockDemo();
    }, reducedMotion.matches ? 80 : 260);
}

mobileStoryStart.addEventListener('click', event => {
    if (window.innerWidth > 768) return;
    event.preventDefault();
    lockDemo();
    steps[0].scrollIntoView({ block: 'start', behavior: 'auto' });
    showStep(0);
    scheduleDemoUnlock();
});

if (!reducedMotion.matches) {
    window.gsap.fromTo(heroReveal, { autoAlpha: 0, y: 24 }, { autoAlpha: 1, y: 0, duration: .9, stagger: .14, ease: 'power3.out', clearProps: 'transform,opacity,visibility' });
    window.gsap.fromTo(headerActions, { autoAlpha: 0, y: -8 }, { autoAlpha: 1, y: 0, duration: .65, stagger: .08, delay: .18, ease: 'power2.out', clearProps: 'transform,opacity,visibility' });
}

const elements = {
    phoneTopbar: document.querySelector('.phone-topbar'),
    telegram: document.querySelector('.tg-layer'),
    tgHeader: document.querySelector('.tg-header'),
    tgMessage: document.querySelector('.tg-message'),
    tgActions: document.querySelector('.tg-actions'),
    tgStart: document.querySelector('.tg-start'),
    tgPointer: document.querySelector('.tg-pointer'),
    app: document.querySelector('.ft-app'),
    homeHeader: document.querySelector('.ft-home-header'),
    homeReveal: [...document.querySelectorAll('.ft-reveal')],
    tabbar: document.querySelector('.ft-tabbar'),
    fab: document.querySelector('.ft-fab'),
    fabPointer: document.querySelector('.fab-pointer'),
    scrim: document.querySelector('.ft-scrim'),
    draft: document.querySelector('.ft-draft'),
    draftCard: document.querySelector('.ft-draft-card'),
    upload: document.querySelector('.ft-upload'),
    dishPhoto: document.querySelector('.ft-dish-photo'),
    dishImage: document.querySelector('.ft-dish-image'),
    scanAction: document.querySelector('.ft-scan-action'),
    scanActionReady: document.querySelector('.scan-action-ready'),
    scanActionLoading: document.querySelector('.scan-action-loading'),
    draftTitleEmpty: document.querySelector('.draft-title-empty'),
    draftTitleFilled: document.querySelector('.draft-title-filled'),
    analysisOverlay: document.querySelector('.ft-analysis-overlay'),
    scanLine: document.querySelector('.ft-scan-line'),
    markers: [...document.querySelectorAll('.ft-marker')],
    analysisStatus: document.querySelector('.ft-analysis-status'),
    analysisProgress: document.querySelector('.ft-analysis-status > i b'),
    analysisLoading: document.querySelector('.analysis-copy-loading'),
    analysisDone: document.querySelector('.analysis-copy-done'),
    draftName: document.querySelector('[data-draft-name]'),
    draftWeight: document.querySelector('[data-draft-weight]'),
    draftSummary: [...document.querySelectorAll('.ft-draft-summary > span')],
    save: document.querySelector('.ft-save'),
    saveLabel: document.querySelector('.ft-save span'),
    saveCheck: document.querySelector('.ft-save i'),
    savePointer: document.querySelector('.save-pointer'),
    newMeal: document.querySelector('.ft-new-meal'),
    mealCount: document.querySelector('.ft-meal-count'),
    mealCalories: document.querySelector('[data-meal-calories]'),
    breakfastCalories: document.querySelector('[data-meal="breakfast"] b'),
    gauge: document.querySelector('.ft-gauge'),
    homePercent: document.querySelector('[data-home-percent]'),
    homeCalories: document.querySelector('[data-home-calories]'),
    homeLeft: document.querySelector('[data-home-left]'),
    homeProtein: document.querySelector('[data-home-protein]'),
    homeFat: document.querySelector('[data-home-fat]'),
    homeCarbs: document.querySelector('[data-home-carbs]'),
    macroBars: [...document.querySelectorAll('.ft-macro > i b')],
};

const draftOutputs = {
    calories: [...document.querySelectorAll('[data-draft-calories]')],
    protein: [...document.querySelectorAll('[data-draft-protein]')],
    fat: [...document.querySelectorAll('[data-draft-fat]')],
    carbs: [...document.querySelectorAll('[data-draft-carbs]')],
};

const draftState = { calories: 0, protein: 0, fat: 0, carbs: 0 };
const homeState = { calories: 760, protein: 42, fat: 28, carbs: 96, progress: 38 };

function renderDraftState() {
    for (const [key, outputs] of Object.entries(draftOutputs)) {
        outputs.forEach(output => {
            const value = Math.round(draftState[key]);
            output.textContent = value > 0 ? value : '';
        });
    }
}

function renderHomeState() {
    elements.homeCalories.textContent = numberFormat.format(Math.round(homeState.calories));
    elements.homeLeft.textContent = numberFormat.format(Math.max(0, Math.round(2000 - homeState.calories)));
    elements.homeProtein.textContent = Math.round(homeState.protein);
    elements.homeFat.textContent = Math.round(homeState.fat);
    elements.homeCarbs.textContent = Math.round(homeState.carbs);
    elements.homePercent.textContent = Math.round(homeState.progress);
    elements.breakfastCalories.textContent = '760';
    elements.mealCalories.textContent = Math.round(Math.max(0, homeState.calories - 760));
    elements.gauge.style.setProperty('--day-progress', homeState.progress);
    elements.macroBars[0].style.width = `${homeState.protein / 80 * 100}%`;
    elements.macroBars[1].style.width = `${homeState.fat / 65 * 100}%`;
    elements.macroBars[2].style.width = `${homeState.carbs / 240 * 100}%`;
}

renderHomeState();

function buildMasterTimeline() {
    const { gsap } = window;
    gsap.set(phoneMotion, { autoAlpha: 0, y: 26, scale: .95 });
    gsap.set(elements.phoneTopbar, { autoAlpha: 0 });
    gsap.set([elements.tgHeader, elements.tgMessage, elements.tgActions], { autoAlpha: 0, y: 10 });
    gsap.set(elements.tgMessage, { scale: .985 });
    gsap.set(elements.tgPointer, { autoAlpha: 0, y: -8 });
    gsap.set(elements.app, { autoAlpha: 0 });
    gsap.set([elements.homeHeader, ...elements.homeReveal, elements.tabbar, elements.fab], { autoAlpha: 0, y: 10, scale: .98 });
    gsap.set(elements.fabPointer, { autoAlpha: 0, y: -8 });
    gsap.set(elements.scrim, { autoAlpha: 0 });
    gsap.set(elements.draft, { autoAlpha: 0, yPercent: 105 });
    gsap.set(elements.dishPhoto, { autoAlpha: 0 });
    gsap.set(elements.dishImage, { scale: 1.06 });
    gsap.set(elements.scanAction, { autoAlpha: 0, y: 5 });
    gsap.set(elements.scanActionLoading, { autoAlpha: 0 });
    gsap.set(elements.draftTitleFilled, { autoAlpha: 0, y: 3 });
    gsap.set(elements.analysisOverlay, { autoAlpha: 0 });
    gsap.set(elements.scanLine, { autoAlpha: 0, top: '18%' });
    gsap.set(elements.markers, { autoAlpha: 0, scale: .8 });
    gsap.set(elements.analysisDone, { autoAlpha: 0 });
    gsap.set(elements.savePointer, { autoAlpha: 0, y: -8 });
    gsap.set(elements.saveCheck, { autoAlpha: 0 });
    gsap.set(elements.newMeal, { height: 0, autoAlpha: 0 });
    gsap.set(elements.mealCount, { display: 'none', autoAlpha: 0 });

    const timeline = gsap.timeline({ paused: true, defaults: { ease: 'sine.inOut' } });

    timeline
        .to(phoneMotion, { autoAlpha: 1, y: 0, scale: 1, duration: 1.45, ease: 'power2.out' })
        .to(elements.phoneTopbar, { autoAlpha: 1, duration: .45 }, .55)
        .to(elements.tgHeader, { autoAlpha: 1, y: 0, duration: .58 }, .82)
        .to(elements.tgMessage, { autoAlpha: 1, y: 0, scale: 1, duration: .65 }, 1.38)
        .to(elements.tgActions, { autoAlpha: 1, y: 0, duration: .58 }, 1.98)
        .to(elements.tgPointer, { autoAlpha: 1, y: 0, duration: .42 }, 2.55)
        .to(elements.tgStart, { scale: 1.02, boxShadow: '0 12px 26px rgba(43,135,200,.26)', duration: .4 }, 2.55)
        .addLabel('telegram', 3)

        .to(elements.tgPointer, { y: 37, duration: .28 }, 'telegram')
        .to(elements.tgStart, { y: 3, scale: .96, boxShadow: '0 2px 5px rgba(43,135,200,.14)', duration: .18 }, 'telegram+=.12')
        .to(elements.tgStart, { y: 0, scale: 1.015, boxShadow: '0 12px 24px rgba(43,135,200,.28)', duration: .22 })
        .to(elements.tgPointer, { autoAlpha: 0, duration: .18 }, '<')
        .set(elements.app, { autoAlpha: 1 }, 'telegram+=.72')
        .to(elements.telegram, { autoAlpha: 0, y: -64, filter: 'blur(5px)', duration: .72 }, 'telegram+=.62')
        .to(elements.homeHeader, { autoAlpha: 1, y: 0, scale: 1, duration: .4 }, 'telegram+=.92')
        .to(elements.homeReveal, { autoAlpha: 1, y: 0, scale: 1, duration: .45, stagger: .14 }, 'telegram+=1.08')
        .to([elements.tabbar, elements.fab], { autoAlpha: 1, y: 0, scale: 1, duration: .42, stagger: .1 }, 'telegram+=1.56')
        .addLabel('openApp', 5.1)

        .to(elements.fabPointer, { autoAlpha: 1, y: 0, duration: .28 }, 'openApp')
        .to(elements.fab, { scale: 1.07, boxShadow: '0 10px 24px rgba(17,17,17,.2)', duration: .28 }, 'openApp')
        .to(elements.fabPointer, { y: 43, duration: .26 }, 'openApp+=.32')
        .to(elements.fab, { y: 3, scale: .94, boxShadow: '0 2px 5px rgba(17,17,17,.12)', duration: .17 }, 'openApp+=.45')
        .to(elements.fab, { y: 0, scale: 1, duration: .22 })
        .to(elements.fabPointer, { autoAlpha: 0, duration: .15 }, '<')
        .to(elements.scrim, { autoAlpha: 1, duration: .25 }, 'openApp+=.78')
        .to(elements.draft, { autoAlpha: 1, yPercent: 0, duration: .72, ease: 'power3.out' }, 'openApp+=.82')
        .from(elements.draftCard.children, { autoAlpha: 0, y: 9, scale: .985, duration: .32, stagger: .06 }, 'openApp+=1.25')
        .to(elements.upload, { scale: 1.025, boxShadow: '0 10px 25px rgba(70,100,40,.12)', duration: .3 }, 'openApp+=1.74')
        .to(elements.upload, { autoAlpha: 0, scale: .95, duration: .32 }, 'openApp+=2.04')
        .to(elements.dishPhoto, { autoAlpha: 1, duration: .48 }, 'openApp+=1.98')
        .to(elements.dishImage, { scale: 1, duration: .68, ease: 'power2.out' }, 'openApp+=1.98')
        .to(elements.scanAction, { autoAlpha: 1, y: 0, duration: .34 }, 'openApp+=2.38')
        .addLabel('addPhoto', 7.84)

        .to(elements.scanActionReady, { autoAlpha: 0, duration: .18 }, 'addPhoto+=.04')
        .to(elements.scanActionLoading, { autoAlpha: 1, duration: .22 }, 'addPhoto+=.12')
        .to(elements.analysisOverlay, { autoAlpha: 1, duration: .3 }, 'addPhoto')
        .to(elements.analysisStatus, { height: 27, autoAlpha: 1, marginBottom: 7, duration: .34 }, 'addPhoto')
        .to(elements.scanLine, { autoAlpha: 1, duration: .22 }, 'addPhoto+=.2')
        .to(elements.analysisProgress, { width: '28%', duration: .55 }, 'addPhoto+=.2')
        .to(elements.scanLine, { top: '48%', duration: .72, ease: 'power1.inOut' }, 'addPhoto+=.42')
        .to(elements.markers, { autoAlpha: 1, scale: 1, duration: .3, stagger: .08 }, 'addPhoto+=.78')
        .to(elements.analysisProgress, { width: '67%', duration: .55 }, 'addPhoto+=.8')
        .to(elements.scanLine, { top: '82%', duration: .72, ease: 'power1.inOut' }, 'addPhoto+=1.16')
        .to(elements.analysisProgress, { width: '100%', duration: .55 }, 'addPhoto+=1.35')
        .to([elements.scanLine, ...elements.markers], { autoAlpha: 0, duration: .3 }, 'addPhoto+=1.84')
        .to(elements.analysisLoading, { autoAlpha: 0, duration: .2 }, 'addPhoto+=1.86')
        .to(elements.analysisDone, { autoAlpha: 1, duration: .25 }, 'addPhoto+=1.98')
        .to(elements.scanAction, { autoAlpha: 0, y: -4, height: 0, minHeight: 0, marginBottom: 0, paddingTop: 0, paddingBottom: 0, duration: .28 }, 'addPhoto+=1.82')
        .to(elements.draftTitleEmpty, { autoAlpha: 0, y: -3, duration: .2 }, 'addPhoto+=1.9')
        .to(elements.draftTitleFilled, { autoAlpha: 1, y: 0, duration: .28 }, 'addPhoto+=2')
        .to(elements.analysisOverlay, { autoAlpha: .12, duration: .3 }, 'addPhoto+=1.9')
        .addLabel('analysis', 10.12)

        .to(elements.analysisOverlay, { autoAlpha: 0, duration: .32 }, 'analysis')
        .set(elements.draftName, { value: 'Куриный боул с рисом' }, 'analysis+=.18')
        .set(elements.draftWeight, { value: '340' }, 'analysis+=.28')
        .fromTo(elements.draftSummary[0], { boxShadow: '0 0 0 0 rgba(109,187,53,0)' }, { boxShadow: '0 0 0 3px rgba(109,187,53,.16)', duration: .25, yoyo: true, repeat: 1 }, 'analysis+=.48')
        .to(draftState, { calories: 486, duration: .52, ease: 'power2.out', onUpdate: renderDraftState }, 'analysis+=.48')
        .to(draftState, { protein: 38, duration: .42, ease: 'power2.out', onUpdate: renderDraftState }, 'analysis+=1.05')
        .to(draftState, { fat: 17, duration: .42, ease: 'power2.out', onUpdate: renderDraftState }, 'analysis+=1.5')
        .to(draftState, { carbs: 45, duration: .42, ease: 'power2.out', onUpdate: renderDraftState }, 'analysis+=1.95')
        .to(elements.draftSummary.slice(1), { y: -2, duration: .18, stagger: .44, yoyo: true, repeat: 1 }, 'analysis+=1.02')
        .to(elements.save, { color: '#111', backgroundColor: '#63c82b', boxShadow: '0 10px 22px rgba(99,200,43,.24)', scale: 1.01, duration: .38 }, 'analysis+=2.35')
        .set(elements.save, { attr: { 'aria-disabled': 'false' } }, 'analysis+=2.35')
        .addLabel('fillFields', 12.88)

        .to(elements.savePointer, { autoAlpha: 1, y: 0, duration: .28 }, 'fillFields')
        .to(elements.savePointer, { y: 40, duration: .25 }, 'fillFields+=.3')
        .to(elements.save, { y: 3, scale: .96, boxShadow: '0 2px 5px rgba(109,187,53,.14)', duration: .17 }, 'fillFields+=.42')
        .to(elements.save, { y: 0, scale: 1, backgroundColor: '#63c82b', duration: .24 })
        .to(elements.savePointer, { autoAlpha: 0, duration: .16 }, '<')
        .to(elements.saveLabel, { autoAlpha: 0, duration: .15 }, 'fillFields+=.72')
        .set(elements.saveCheck, { display: 'inline' }, 'fillFields+=.72')
        .to(elements.saveCheck, { autoAlpha: 1, duration: .2 }, 'fillFields+=.76')
        .to(elements.scrim, { autoAlpha: 0, duration: .45 }, 'fillFields+=1.04')
        .to(elements.draft, { yPercent: 108, duration: .7, ease: 'power3.inOut' }, 'fillFields+=1.04')
        .to(elements.newMeal, { height: 52, autoAlpha: 1, paddingTop: 6, paddingBottom: 6, duration: .56, ease: 'power3.out' }, 'fillFields+=1.62')
        .set(elements.mealCount, { display: 'block', textContent: '1 блюдо' }, 'fillFields+=1.72')
        .fromTo(elements.mealCount, { autoAlpha: .45, y: -2 }, { autoAlpha: 1, y: 0, duration: .22 }, 'fillFields+=1.72')
        .to(homeState, { calories: 1246, protein: 80, fat: 45, carbs: 141, progress: 62, duration: 1.18, ease: 'power2.inOut', onUpdate: renderHomeState }, 'fillFields+=2.02')
        .addLabel('saveResult', 16.22);

    timeline.pause(0);
    return timeline;
}

const masterTimeline = buildMasterTimeline();

function playScene(index) {
    const interrupted = Boolean(sceneTween?.isActive());
    sceneTween?.kill();
    const label = sceneLabels[index];

    if (reducedMotion.matches) {
        masterTimeline.seek(label, false);
        if (window.innerWidth <= 768) {
            sceneTween = window.gsap.fromTo(phoneMotion, {
                autoAlpha: .35,
                y: 6,
            }, {
                autoAlpha: 1,
                y: 0,
                duration: .18,
                ease: 'power1.out',
                overwrite: true,
            });
        }
        return;
    }

    const distance = Math.abs(masterTimeline.labels[label] - masterTimeline.time());
    const fastTrack = interrupted || distance > 3.5;
    const duration = fastTrack
        ? Math.max(.6, Math.min(1.4, distance * .22))
        : Math.max(1.4, Math.min(5.2, distance * .82));

    sceneTween = masterTimeline.tweenTo(label, {
        duration,
        ease: fastTrack ? 'power2.out' : 'sine.inOut',
        overwrite: true,
    });
}

function showStep(index) {
    if (index === activeIndex) return;
    steps.forEach((step, stepIndex) => step.classList.toggle('is-active', stepIndex === index));
    counter.textContent = String(index + 1).padStart(2, '0');
    activeIndex = index;
}

function commitActiveScene() {
    if (activeIndex < 0 || activeIndex === committedIndex) return;
    committedIndex = activeIndex;
    playScene(activeIndex);
}

function syncStep() {
    scrollFrame = null;
    const isMobile = window.innerWidth <= 768;
    const activationLine = isMobile
        ? getMobileActivationLine()
        : window.innerHeight / 2;
    const boxes = steps.map(step => step.getBoundingClientRect());

    if (isMobile && mobileStoryStart.getBoundingClientRect().bottom > 0) lockDemo();
    productStory.classList.toggle('is-demo-locked', isMobile && !demoUnlocked);

    const isBeforeFirstStep = isMobile
        ? boxes[0].top > activationLine
        : boxes[0].top > window.innerHeight * .8;

    if (isBeforeFirstStep) {
        clearTimeout(demoUnlockTimer);
        demoUnlockTimer = null;
        sceneTween?.kill();
        masterTimeline.pause(0);
        if (isMobile) steps.forEach(step => step.classList.remove('is-active'));
        activeIndex = -1;
        committedIndex = -1;
        return;
    }

    const centered = boxes.findIndex(box => box.top <= activationLine && box.bottom > activationLine);
    showStep(centered >= 0 ? centered : (boxes[0].top > activationLine ? 0 : steps.length - 1));
    if (isMobile) scheduleDemoUnlock();
}

function easeInOut(progress) {
    return (1 - Math.cos(Math.PI * progress)) / 2;
}

function snapToClosestStep() {
    if (activeIndex < 0) return;
    if (window.innerWidth <= 980 || reducedMotion.matches) {
        commitActiveScene();
        return;
    }

    const start = window.scrollY;
    const target = start + steps[activeIndex].getBoundingClientRect().top;
    const distance = Math.abs(target - start);
    if (distance < 2) {
        commitActiveScene();
        return;
    }
    if (distance > window.innerHeight * .78) return;

    const startedAt = performance.now();
    const duration = Math.min(900, 560 + distance * .28);

    function tick(now) {
        const progress = Math.min(1, (now - startedAt) / duration);
        window.scrollTo(0, start + (target - start) * easeInOut(progress));
        if (progress < 1) {
            snapFrame = requestAnimationFrame(tick);
            return;
        }

        snapFrame = null;
        syncStep();
        commitActiveScene();
    }

    snapFrame = requestAnimationFrame(tick);
}

function cancelSnap() {
    cancelAnimationFrame(snapFrame);
    snapFrame = null;
}

function handleScroll() {
    if (pageIsRestoring) return;
    if (!scrollFrame) scrollFrame = requestAnimationFrame(syncStep);
    if (snapFrame) return;
    clearTimeout(snapTimeout);
    snapTimeout = setTimeout(snapToClosestStep, 160);
}

window.addEventListener('pageshow', () => {
    pageIsRestoring = true;
    cancelAnimationFrame(scrollFrame);
    scrollFrame = null;
    cancelSnap();
    clearTimeout(snapTimeout);
    pageRoot.style.scrollBehavior = 'auto';
    window.scrollTo(0, 0);
    requestAnimationFrame(() => {
        window.scrollTo(0, 0);
        requestAnimationFrame(() => {
            window.scrollTo(0, 0);
            pageRoot.style.removeProperty('scroll-behavior');
            pageIsRestoring = false;
            syncStep();
        });
    });
});

window.addEventListener('scroll', handleScroll, { passive: true });
window.addEventListener('wheel', cancelSnap, { passive: true });
window.addEventListener('touchstart', cancelSnap, { passive: true });
window.addEventListener('resize', syncStep);
syncStep();

console.assert(window.gsap && steps.length === 6 && sceneLabels.every(label => label in masterTimeline.labels), 'GSAP product story is incomplete.');
console.assert(productStory && mobileStoryStart, 'Mobile story controls are incomplete.');
console.assert(Number.isFinite(getMobileActivationLine()), 'Mobile activation line must be measurable.');
console.assert(sceneLabels.every((label, index) => index === 0 || masterTimeline.labels[label] > masterTimeline.labels[sceneLabels[index - 1]]), 'Scene labels must be sequential.');
console.assert(easeInOut(0) === 0 && easeInOut(1) === 1, 'Step navigation easing must preserve its endpoints.');
console.assert(!('scrollRestoration' in window.history) || window.history.scrollRestoration === 'manual', 'Reload must start at the page header.');
