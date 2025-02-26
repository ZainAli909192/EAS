// Get DOM elements
const clockInBtn = document.getElementById('clock-in-btn');
const clockOutBtn = document.getElementById('clock-out-btn');
const cameraFeed = document.getElementById('camera-feed');
const cameraCanvas = document.getElementById('camera-canvas');
const captureBtn = document.getElementById('capture-btn');
const capturedPhoto = document.getElementById('captured-photo');
const cameraPreview = document.getElementById('camera-preview');

let stream = null;

// Function to start the camera
async function startCamera() {
  try {
    // Access the user's camera
    cameraPreview.style.display = 'block';
    stream = await navigator.mediaDevices.getUserMedia({ video: true });
    cameraFeed.srcObject = stream;
    cameraFeed.style.display = 'block';
    captureBtn.style.display = 'block';
  } catch (error) {
    console.error('Error accessing the camera:', error);
    alert('Unable to access the camera. Please ensure you have granted permission.');
  }
}

// Function to stop the camera
function stopCamera() {
  if (stream) {
    const tracks = stream.getTracks();
    tracks.forEach(track => track.stop());
    cameraFeed.srcObject = null;
    cameraFeed.style.display = 'none';
    captureBtn.style.display = 'none';
    cameraPreview.style.display = 'none';
  }
}

// Function to capture a photo
function capturePhoto() {
  const context = cameraCanvas.getContext('2d');
  cameraCanvas.width = cameraFeed.videoWidth;
  cameraCanvas.height = cameraFeed.videoHeight;
  context.drawImage(cameraFeed, 0, 0, cameraCanvas.width, cameraCanvas.height);

  // Display the captured photo
  const photoDataUrl = cameraCanvas.toDataURL('image/png');
  capturedPhoto.src = photoDataUrl;
  capturedPhoto.style.display = 'block';

  // Stop the camera after capturing the photo
  stopCamera();
}

// Event Listeners
clockInBtn.addEventListener('click', () => {
  startCamera();
});

clockOutBtn.addEventListener('click', () => {
  startCamera();
});

captureBtn.addEventListener('click', () => {
  capturePhoto();
});