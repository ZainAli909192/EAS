
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

    // Convert canvas to blob
    cameraCanvas.toBlob(async (blob) => {
        try {
            const formData = new FormData();
            formData.append('photo', blob, 'attendance_photo.png');
            formData.append('action', isClockIn ? 'clock_in' : 'clock_out');
            // formData.append('employee_id', /* Get from session */);
            formData.append('date', new Date().toISOString().split('T')[0]);

            const response = await fetch('../api/routes/attendance.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            // const result = await response.text();
            // alert(result)

            if (result.status === 'success') {
                alert(isClockIn ? 'Clocked in successfully!' : 'Clocked out successfully!');
                updateAttendanceTable();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save attendance record'+error);
        } finally {
            stopCamera();
        }
    }, 'image/png');
}

// Update attendance table
async function updateAttendanceTable() {
  try {
      const response = await fetch('../api/routes/attendance.php');
      const data = await response.json();

      if (data.status === 'success') {
          const tableBody = document.getElementById('attendance-table-body');
          tableBody.innerHTML = '';

          data.attendance.forEach(record => {
              const row = document.createElement('tr');
              row.innerHTML = `
                  <td>${record.date}</td>
                  <td>${record.time_in || '-'}</td>
                  <td>${record.time_out || '-'}</td>
                  <td>${record.photo_in ? `<img src="../api/attendance_pics/${record.photo_in}" width="50">` : '-'}</td>
                  <td>${record.photo_out ? `<img src="../api/attendance_pics/${record.photo_out}" width="50">` : '-'}</td>
                  <td>${record.attendance_type}</td>
              `;
              tableBody.appendChild(row);
          });
      }
      if (data.stats) {
                      updateStatistics(data.stats);
                  }
  } catch (error) {
      console.error('Error fetching attendance:', error);
  }
}

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

