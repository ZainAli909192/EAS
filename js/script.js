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
