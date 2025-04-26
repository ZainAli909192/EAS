
const feedbackContainer = document.querySelector('.feedback-container');
const feedbackItems = document.querySelectorAll('.feedback-item');
const leftBtn = document.querySelector('.left-btn');
const rightBtn = document.querySelector('.right-btn');

let currentIndex = 0;
const itemsToShow = 2; // Number of items to show at once
const itemWidth = feedbackItems[0].offsetWidth + 20; // Item width including margin
const totalItems = feedbackItems.length;
 
function updateSliderPosition() {
    const offset = -currentIndex * itemWidth;
    feedbackContainer.style.transform = `translateX(${offset}px)`;
}

function slideLeft() {
    if (currentIndex > 0) {
      currentIndex -= 1; // Changed from itemsToShow to 1
      updateSliderPosition();
    }
  }
  

  function slideRight() {
    if (currentIndex < totalItems - itemsToShow) {
      currentIndex += itemsToShow;
      updateSliderPosition();
    } else {
      currentIndex = 0; // Reset currentIndex to 0 when reaching the end
      updateSliderPosition();
    }
  }
  function slideRightSlow() {
    if (currentIndex < totalItems - 3) {
      currentIndex += 1;  
      updateSliderPosition();
    } else {
      currentIndex = 0; // Reset currentIndex to 0 when reaching the end
      updateSliderPosition();
    }
  }
  
  let isAutomaticSliding = true;
  let timeoutId;
  
  setInterval(() => {
    if (isAutomaticSliding) {
      slideRightSlow();
    } 
    // if (!isAutomaticSliding) {
    //   slideLeft();
    // } 
  }, 3000);
  
  leftBtn.addEventListener('click', () => {
    isAutomaticSliding = false;
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      isAutomaticSliding = true;
    }, 4000); // Resume automatic sliding after 10 seconds
    slideLeft();
  });
  
  rightBtn.addEventListener('click', () => {
    isAutomaticSliding = false;
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      isAutomaticSliding = true;
    }, 4000); // Resume automatic sliding after 10 seconds
    slideRight();
  });
  
// Set the initial position
updateSliderPosition();

