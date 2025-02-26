const navbarToggle = document.querySelector('.navbar-toggle');
const navbarLinks = document.querySelector('.navbar-links');

// Toggle the visibility of the navbar links when the toggle button is clicked
navbarToggle.addEventListener('click', () => {
  navbarLinks.classList.toggle('active');
});

// Close the navbar when clicking outside the navbar area
document.body.addEventListener('click', (e) => {
  if (!navbarToggle.contains(e.target) && !navbarLinks.contains(e.target)) {
    navbarLinks.classList.remove('active');
  }
});

// Handle click on navbar links, toggle active class
navbarLinks.addEventListener('click', (e) => {
  if (e.target.tagName === 'A') {
    document.querySelectorAll('.navbar-links a').forEach(link => link.classList.remove('active'));

    // Toggle navbar links visibility on small screens
    navbarLinks.classList.remove('active');
    e.target.classList.add('active');
  }
});

// Scroll function to fix navbar on scroll
function scrollFunction() {
  if (window.scrollY > 1) {
    document.querySelector('.navbar').classList.add('fixed-nav');
  } else {
    document.querySelector('.navbar').classList.remove('fixed-nav');
}
}
// Attach the scroll function to the window scroll event
window.addEventListener('scroll', scrollFunction);

// Initial call to handle page load state
scrollFunction();
