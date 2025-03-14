document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector("header");
    const navbar = document.querySelector("header nav");
    const hamburger = document.getElementById("burger");
    const mainContent = document.querySelector("main");

    // Toggle Sidebar on Click
    hamburger.addEventListener("click", function () {
        navbar.classList.toggle("active");
        
        // if (sidebar.classList.contains("active")) {
        //     mainContent.style.marginLeft = "250px";
        // } else {
        //     mainContent.style.marginLeft = "0";
        // }

      });

    // Close Sidebar if Clicking Outside
    document.addEventListener("click", function (event) {
        if (!sidebar.contains(event.target) && !hamburger.contains(event.target)) {
            sidebar.classList.remove("active");
            mainContent.style.marginLeft = "0";
        }
    });

    // Close Sidebar When Clicking a Menu Item (on mobile)
    document.querySelectorAll("nav ul li a").forEach(link => {
        link.addEventListener("click", function () {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove("active");
                mainContent.style.marginLeft = "0";
            }
        });

      });
});

// random circle showing in hero
setInterval(function () {
    const randomDot = document.querySelector(".random-dot");
    const x = Math.floor(Math.random() * (window.innerWidth * 0.5));
    const y = Math.floor(Math.random() * (window.innerHeight * 0.6));
  
    // Update the position and opacity
    randomDot.style.top = `${y}px`;
    randomDot.style.left = `${x}px`;
    randomDot.style.opacity = Math.random() > 0.2 ? 1 : 0;
  }, 2000);

//   contact js
// DOM Elements
if(document.querySelector(".open-popup-btn")){
const openPopupBtn = document.querySelector(".open-popup-btn");
const popupOverlay = document.querySelector(".popup-overlay");
const closePopupBtn = document.querySelector(".close-popup-btn");
const contactForm = document.getElementById("contact-form");

// Open Popup
openPopupBtn.addEventListener("click", () => {
  popupOverlay.classList.add("active");
});

// Close Popup
closePopupBtn.addEventListener("click", () => {
  popupOverlay.classList.remove("active");
});

// Close Popup when clicking outside the form
popupOverlay.addEventListener("click", (e) => {
  if (e.target === popupOverlay) {
    popupOverlay.classList.remove("active");
  }
});

// Close Popup on Escape key press
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && popupOverlay.classList.contains("active")) {
    popupOverlay.classList.remove("active");
  }
});

// Handle Form Submission
contactForm.addEventListener("submit", (e) => {
  e.preventDefault();
  const formData = new FormData(contactForm);
  const data = Object.fromEntries(formData);
  alert("Message sent successfully!");
  popupOverlay.classList.remove("active");
  contactForm.reset();
});
} 

if(document.querySelector('.typing-effect')){

  const heading = document.querySelector('.typing-effect');
  const text = heading.textContent;
  heading.textContent = ''; // Clear the text initially
  
  let index = 0;
  const type = () => {
    if (index < text.length) {
      heading.textContent += text.charAt(index);
      index++;
      setTimeout(type, 50); // Adjust typing speed
    }
  };
  
  setTimeout(type, 1000) ;
}

document.addEventListener('DOMContentLoaded', function() {
  // Wait for 3 seconds, then add the 'loaded' class to the body
  setTimeout(function() { 
    document.body.classList.add('loaded');
  }, 1500); // 3000 milliseconds = 3 seconds
});
// loader file include code 
const loading=async ()=>{
  const loaderFile=await fetch("./loader.html");
  const data= await loaderFile.text();
  document.getElementById('loader-placeholder').innerHTML =  data;
}
loading()

// const sidenav=async ()=>{
//   const loaderFile=await fetch("./sidenav.html");
//   const data= await loaderFile.text();
//   document.getElementById('sidenav-placeholder').innerHTML =  data;
// }
// sidenav()

const sidenav = async () => {
  try {
    // Fetch the sidenav content
    const loaderFile = await fetch("./sidenav.html");
    const data = await loaderFile.text();

    // Insert the sidenav content into the placeholder
    document.getElementById('sidenav-placeholder').innerHTML = data;

    // Attach event listeners after the sidenav is loaded
    attachSidenavEventListeners();
  } catch (error) {
    console.error("Error loading sidenav:", error);
  }
};

// Function to attach event listeners for the sidenav
const attachSidenavEventListeners = () => {
  const sidebar = document.querySelector("header");
  const navbar = document.querySelector("header nav");
  const hamburger = document.getElementById("burger");
  const mainContent = document.querySelector("main");

  // Toggle Sidebar on Click
  if (hamburger) {
    hamburger.addEventListener("click", function () {
      navbar.classList.toggle("active");
    });
  }

  // Close Sidebar if Clicking Outside
  document.addEventListener("click", function (event) {
    if (!sidebar.contains(event.target) && !hamburger.contains(event.target)) {
      navbar.classList.remove("active");
    }
  });

  // Close Sidebar When Clicking a Menu Item (on mobile)
  document.querySelectorAll("nav ul li a").forEach(link => {
    link.addEventListener("click", function () {
      if (window.innerWidth <= 768) {
        navbar.classList.remove("active");
      }
    });
  });
};

// Load the sidenav
sidenav();