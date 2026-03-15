/* =========================================
   MOBILE MENU SHOW/HIDE
   ========================================= */
const navMenu = document.getElementById('nav-menu'),
  navToggle = document.getElementById('nav-toggle'),
  navClose = document.getElementById('nav-close'),
  navOverlay = document.getElementById('nav-overlay'),
  navLinks = document.querySelectorAll('.nav-link');

/* Show Menu */
if (navToggle) {
  navToggle.addEventListener('click', () => {
    navMenu.classList.add('show-menu');
    if (navOverlay) navOverlay.classList.add('show-overlay');
  });
}

/* Hide Menu */
if (navClose) {
  navClose.addEventListener('click', () => {
    navMenu.classList.remove('show-menu');
    if (navOverlay) navOverlay.classList.remove('show-overlay');
  });
}

if (navOverlay) {
  navOverlay.addEventListener('click', () => {
    navMenu.classList.remove('show-menu');
    navOverlay.classList.remove('show-overlay');
  });
}

/* Remove Menu Mobile on Link Click */
const linkAction = () => {
  navMenu.classList.remove('show-menu');
  if (navOverlay) navOverlay.classList.remove('show-overlay');
}
navLinks.forEach(n => n.addEventListener('click', linkAction));

/* =========================================
   CHANGE BACKGROUND HEADER
   ========================================= */
const scrollHeader = () => {
  const header = document.getElementById('header')
  // When the scroll is greater than 50 viewport height, add the scroll-header class to the header tag
  if (this.scrollY >= 50) header.classList.add('scroll-header'); else header.classList.remove('scroll-header')
}
window.addEventListener('scroll', scrollHeader)

/* =========================================
   SCROLL REVEAL ANIMATION (Optional simple intersection observer)
   ========================================= */
const observerOptions = {
  threshold: 0.1
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      observer.unobserve(entry.target);
    }
  });
}, observerOptions);

const elementsToAnimate = document.querySelectorAll('.section-title, .section-description, .service-card, .product-content, .product-image-wrapper');
elementsToAnimate.forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
  observer.observe(el);
});

// CSS for the animation state
const styleSheet = document.createElement("style");
styleSheet.innerText = `
    .visible {
        opacity: 1 !important;
        transform: translateY(0) !important;
    }
`;
document.head.appendChild(styleSheet);
