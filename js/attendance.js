
// new code with endpoint link 

const clockInBtn = document.getElementById('clock-in-btn');
const clockOutBtn = document.getElementById('clock-out-btn');
const cameraFeed = document.getElementById('camera-feed');
const cameraCanvas = document.getElementById('camera-canvas');
const captureBtn = document.getElementById('capture-btn');
const cameraPreview = document.getElementById('camera-preview');

let stream = null;
let isClockIn = true; // Flag to track if we're clocking in or out

// Function to start the camera
async function startCamera(clockIn) {
    try {
        isClockIn = clockIn;
        cameraPreview.style.display = 'block';
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { width: 640, height: 480 } 
        });
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
        stream.getTracks().forEach(track => track.stop());
        cameraFeed.srcObject = null;
        cameraFeed.style.display = 'none';
        captureBtn.style.display = 'none';
        cameraPreview.style.display = 'none';
    }
}

// Function to capture a photo and send to server
async function capturePhoto() {
    const context = cameraCanvas.getContext('2d');
    cameraCanvas.width = cameraFeed.videoWidth;
    cameraCanvas.height = cameraFeed.videoHeight;
    context.drawImage(cameraFeed, 0, 0, cameraCanvas.width, cameraCanvas.height);

    try {
        // Get location (with error handling if it fails)
        let location = null;
        try {
            location = await getLocation();
            console.warn(location.address);
            
        } catch (locationError) {
            console.warn('Location error (proceeding without location):', locationError.message);
            // You might want to show a warning to the user here
        }

        // Convert canvas to blob and prepare form data
        const blob = await new Promise(resolve => {
            cameraCanvas.toBlob(resolve, 'image/png');
        });

        const formData = new FormData();
        formData.append('photo', blob, 'attendance_photo.png');
        formData.append('action', isClockIn ? 'clock_in' : 'clock_out');
        formData.append('date', new Date().toISOString().split('T')[0]);

        // Add full address if available
        if (location) {
            formData.append('full_address', location.address || '');
        }
        // Submit the datax
        const response = await fetch('../api/routes/attendance.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        console.warn(result);
        
        if (result.status === 'success') {
            await updateAttendanceTable();
            showAlertPopup("Success",isClockIn ? 'Clocked in successfully!' : 'Clocked out successfully!');
        } else {
            throw new Error(result.message || 'Unknown error from server');
        }
    } catch (error) {
        console.error('Error in capturePhoto:', error);
        alert('Failed to save attendance record: ' + (error.message || 'Unknown error'));
    } finally {
        stopCamera();
    }
}
// async function capturePhoto() {
//     const context = cameraCanvas.getContext('2d');
//     cameraCanvas.width = cameraFeed.videoWidth;
//     cameraCanvas.height = cameraFeed.videoHeight;
//     context.drawImage(cameraFeed, 0, 0, cameraCanvas.width, cameraCanvas.height);

//     try {
//         // Get location (with error handling if it fails)
//         let location = null;
//         try {
//             location = await getLocation();
//         } catch (locationError) {
//             console.warn('Location error (proceeding without location):', locationError.message);
//             // You might want to show a warning to the user here
//         }

//         // Convert canvas to blob and prepare form data
//         const blob = await new Promise(resolve => {
//             cameraCanvas.toBlob(resolve, 'image/png');
//         });

//         const formData = new FormData();
//         formData.append('photo', blob, 'attendance_photo.png');
//         formData.append('action', isClockIn ? 'clock_in' : 'clock_out');
//         formData.append('date', new Date().toISOString().split('T')[0]);

//         // Add location data if available
//         if (location) {
//             formData.append('latitude', location.latitude);
//             formData.append('longitude', location.longitude);
//             formData.append('city', location.city || '');
//             formData.append('region', location.region || '');
//             formData.append('country', location.country || '');
//             formData.append('full_address', location.address || '');
//         }

//         // Submit the data
//         const response = await fetch('../api/routes/attendance.php', {
//             method: 'POST',
//             body: formData
//         });

//         if (!response.ok) {
//             throw new Error(`HTTP error! status: ${response.status}`);
//         }

//         const result = await response.json();
        
//         if (result.status === 'success') {
//             await updateAttendanceTable();
//             alert(isClockIn ? 'Clocked in successfully!' : 'Clocked out successfully!');
//         } else {
//             throw new Error(result.message || 'Unknown error from server');
//         }
//     } catch (error) {
//         console.error('Error in capturePhoto:', error);
//         alert('Failed to save attendance record: ' + (error.message || 'Unknown error'));
//     } finally {
//         stopCamera();
//     }
// }


getLocation()
// Function to get current location
async function getLocation() {
    try {
        if (!navigator.geolocation) {
        alert("Allow location to proceed");
            // throw new Error('Geolocation not supported');
            return;
        }

        // Get coordinates
        const position = await new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject);
        });

        const coords = position.coords;
        const location = {
            latitude: coords.latitude,
            longitude: coords.longitude
        };

        // Reverse geocode to get address details
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${location.latitude}&lon=${location.longitude}`
        );
        
        if (!response.ok) {
            throw new Error('Reverse geocoding failed');
        }

        const addressData = await response.json();
        
        // Extract address components
        location.city = addressData.address.city || 
                        addressData.address.town || 
                        addressData.address.village;
        location.region = addressData.address.state || 
                          addressData.address.region;
        location.country = addressData.address.country;
        location.address = addressData.display_name;

        return location;
    } catch (error) {
        
        throw new Error('Error getting location: ' + error.message);
    }
}

function showConfirmationPopup(message, leaveId, action) {
    
    document.getElementById('confirmation-message').textContent = message;
    document.body.classList.add('popup-active');
    const popup = document.getElementById('confirmation-popup');
    popup.style.display = 'block';
    setTimeout(() => popup.classList.add('active'), 10);
}

function hideConfirmationPopup() {
    document.body.classList.remove('popup-active');
    const popup = document.getElementById('confirmation-popup');
    popup.classList.remove('active');
    setTimeout(() => popup.style.display = 'none', 300);
}

function showAlertPopup(title, message) {
    document.getElementById('alert-title').textContent = title;
    document.getElementById('alert-message').textContent = message;
    document.body.classList.add('popup-active');
    const popup = document.getElementById('alert-popup');
    popup.style.display = 'block';
    setTimeout(() => popup.classList.add('active'), 10);
    setTimeout( hideAlertPopup, 2000);
}

function hideAlertPopup() {
    document.body.classList.remove('popup-active');
    const popup = document.getElementById('alert-popup');
    popup.classList.remove('active');
    setTimeout(() => popup.style.display = 'none', 300);
}


// Update attendance table
// async function updateAttendanceTable() {
//     try {
//         const response = await fetch('../api/routes/attendance.php');
//         if (!response.ok) throw new Error('Network response was not ok');
        
//         const data = await response.json();
//         console.log('Attendance data:', data); // Debug logging

//         if (data.status === 'success') {
//             const tableBody = document.getElementById('attendance-table-body');
//             tableBody.innerHTML = '';
//             let i=1;
//             data.attendance.forEach(record => {
//                 const row = document.createElement('tr');
//                 row.innerHTML = `
//                     <td>${i}</td>
//                     <td>${record.date}</td>
//                     <td>${record.time_in ? formatTime(record.time_in) : '-'}</td>
//                     <td>${record.time_out ? formatTime(record.time_out) : '-'}</td>
//                     <td>${record.photo_in ? `<img src="../api/attendance_pics/${record.photo_in}" width="50" loading="lazy">` : '-'}</td>
//                     <td>${record.photo_out ? `<img src="../api/attendance_pics/${record.photo_out}" width="50" loading="lazy">` : '-'}</td>
//                     <td>${record.attendance_type}</td>
//                 `;
//                 tableBody.appendChild(row);
//                 i++;
//             });

//             if (data.stats) {
//                 updateStatistics(data.stats);
//             }
//         }
//     } catch (error) {
//         console.error('Error fetching attendance:', error);
//         alert('Failed to refresh attendance data: ' + error.message);
//     }
// }
// Event Listeners
clockInBtn.addEventListener('click', () => startCamera(true));
clockOutBtn.addEventListener('click', () => startCamera(false));
captureBtn.addEventListener('click', capturePhoto);

// Initialize
document.addEventListener('DOMContentLoaded', updateAttendanceTable);


// Add this new function to format time
function formatTime(timeStr) {
  if (!timeStr || timeStr === '-') return '-';
  const [hours, minutes] = timeStr.split(':');
  const period = hours >= 12 ? 'PM' : 'AM';
  const hours12 = hours % 12 || 12;
  return `${hours12}:${minutes} ${period}`;
}

// Update this function
// async function updateAttendanceTable() {
//   try {
//       const response = await fetch('../api/routes/attendance.php');
//       const data = await response.json();

//       if (data.status === 'success') {
//           const tableBody = document.getElementById('attendance-table-body');
//           tableBody.innerHTML = '';

//           data.attendance.forEach(record => {
//               const row = document.createElement('tr');
//               row.innerHTML = `
//                   <td>${record.date}</td>
//                   <td>${record.time_in ? formatTime(record.time_in) : '-'}</td>
//                   <td>${record.time_out ? formatTime(record.time_out) : '-'}</td>
//                   <td>${record.photo_in ? `<img src="../attendance_pics/${record.photo_in}" width="50">` : '-'}</td>
//                   <td>${record.photo_out ? `<img src="../attendance_pics/${record.photo_out}" width="50">` : '-'}</td>
//                   <td>${record.attendance_type}</td>
//               `;
//               tableBody.appendChild(row);
//           });

//           // Update statistics if they exist
//           if (data.stats) {
//               updateStatistics(data.stats);
//           }
//       }
//   } catch (error) {
//       console.error('Error fetching attendance:', error);
//   }
// }

// Add this new function to update statistics
function updateStatistics(stats) {
  // Update the statistics display
  const statItems = document.querySelectorAll('.stat-item');
  
  // Total Working Days
  statItems[0].querySelector('strong').textContent = stats.total_days;
  
  // Average Clock-In Time (format from 24h to 12h)
  if (stats.avg_clock_in !== 'N/A') {
      const [hours, minutes] = stats.avg_clock_in.split(':');
      const period = hours >= 12 ? 'PM' : 'AM';
      const hours12 = hours % 12 || 12;
      statItems[1].querySelector('strong').textContent = `${hours12}:${minutes} ${period}`;
  } else {
      statItems[1].querySelector('strong').textContent = 'N/A';
  }
  
  // Attendance Rate
  statItems[2].querySelector('strong').textContent = `${stats.attendance_rate}%`;
}


// Add event listeners for filter changes
document.getElementById('attendance-filter-month').addEventListener('change', applyFilters);
document.getElementById('attendance-filter-year').addEventListener('change', applyFilters);
document.getElementById('attendance-filter-rows').addEventListener('change', applyFilters);

// Function to apply filters and refresh data
async function applyFilters() {
    loading();
    const month = document.getElementById('attendance-filter-month').value;
    const year = document.getElementById('attendance-filter-year').value;
    
    try {
        // Build query string based on selected filters
        let queryString = '';
        if (month && month !== '') queryString += `month=${month}`;
        if (year && year !== '') {
            if (queryString) queryString += '&';
            queryString += `year=${year}`;
        }
        const rows = document.getElementById('attendance-filter-rows').value;
        if (rows && rows !== '') {
            if (queryString) queryString += '&';
            queryString += `rows=${rows}`;
        }
        const response = await fetch(`../api/routes/attendance.php?${queryString}`);
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        // const data = await response.text();
        console.log('Filtered attendance data:', data);

        if (data.status === 'success') {
            const tableBody = document.getElementById('attendance-table-body');
            tableBody.innerHTML = '';
            let i = 1;
            
            data.attendance.forEach(record => {
                console.warn(record);                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${i}</td>
                    <td>${record.date}</td>
                    <td>${record.time_in ? formatTime(record.time_in) : '-'}</td>
                    <td>${record.time_out ? formatTime(record.time_out) : '-'}</td>
                    <td>${record.photo_in ? `<img src="../api/attendance_pics/${record.photo_in}" width="50" loading="lazy">` : '-'}</td>
                    <td>${record.photo_out ? `<img src="../api/attendance_pics/${record.photo_out}" width="50" loading="lazy">` : '-'}</td>
                    <td>${record.attendance_type}</td>
                    <td>${record.clock_in_location ? record.clock_in_location : '-'}</td>
                    <td>${record.clock_out_location ? record.clock_out_location : '-'}</td>
                    `;
                    // <td>${record.clock_in_location}</td>
                tableBody.appendChild(row);
                i++;
            });

            if (data.stats) {
                updateStatistics(data.stats);
            }
        }
    } catch (error) {
        console.error('Error applying filters:', error);
        alert('Failed to filter attendance data: ' + error.message);
    }
}

// Modify your existing updateAttendanceTable function to use filters
async function updateAttendanceTable() {
    // Just call applyFilters which will handle both filtered and unfiltered cases
    await applyFilters();
}
applyFilters()
// Add reset filters functionality
document.getElementById('reset-filters').addEventListener('click', () => {
    document.getElementById('attendance-filter-month').value = '';
    document.getElementById('attendance-filter-year').value = '';
    applyFilters();
});