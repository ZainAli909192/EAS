// reporting.js

// departments showing from db
document.addEventListener("DOMContentLoaded", function () {
  // First, load departments from API
  loadDepartments();
  
  // Rest of your existing chart initialization code...
});

async function loadDepartments() {
  try {
      const response = await fetch('../api/routes/department.php');
      const data = await response.json();
      
      if (data.status === 'success') {
          const departmentSelect = document.getElementById('department');
          
          // Clear existing options except the first "All Departments"
          while (departmentSelect.options.length > 1) {
              departmentSelect.remove(1);
          }
          
          // Add departments from API
          data.departments.forEach(dept => {
              const option = document.createElement('option');
              option.value = dept.Department_id;
              option.textContent = dept.Department_name;
              departmentSelect.appendChild(option);
          });
      } else {
          console.error('Failed to load departments:', data.message);
      }
  } catch (error) {
      console.error('Error loading departments:', error);
  }
}

// for departments 
async function updateAttendanceTable() {
  try {
      const response = await fetch('../api/routes/attendance.php');
      const data = await response.json();

      if (data.status === 'success') {
         
          // Update status dropdown if options exist
          if (data.status_options) {
              updateStatusDropdown(data.status_options);
          }
      }
  } catch (error) {
      console.error('Error fetching attendance:', error);
  }
}
updateAttendanceTable()
// New function to update status dropdown
function updateStatusDropdown(statuses) {
  const statusSelect = document.getElementById('status');
  
  
  // Add status options from API
  statuses.forEach(status => {
      const option = document.createElement('option');
      option.value = status.toLowerCase(); // Convert to lowercase for consistency
      option.textContent = status;
      statusSelect.appendChild(option);
  });
}

// Rest of your existing JavaScript code...

document.addEventListener("DOMContentLoaded", function () {
    // Get the canvas element
    const ctx = document.getElementById('attendanceChart').getContext('2d');
  
    // Initialize the chart
    const attendanceChart = new Chart(ctx, {
      type: 'line', // You can use 'bar', 'pie', etc.
      data: { 
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'], // Example labels
        datasets: [{
          label: 'Attendance',
          data: [65, 59, 80, 81, 56, 55, 40], // Example data
          backgroundColor: 'rgba(75, 192, 192, 0.2)',
          borderColor: 'rgba(75, 192, 192, 1)',
          borderWidth: 2,
          fill: true,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Number of Employees'
            }
          },
          x: {
            title: {
              display: true,
              text: 'Months'
            }
          }
        }
      }
    });
  
    // Example: Update chart data when the form is submitted
    document.getElementById('attendance-report-form').addEventListener('submit', function (e) {
      e.preventDefault();
  
      // Fetch data based on form inputs (you can replace this with actual API calls)
      const startDate = document.getElementById('start-date').value;
      const endDate = document.getElementById('end-date').value;
      const department = document.getElementById('department').value;
  
      // Example: Update chart data
      const newData = [70, 65, 85, 90, 60, 70, 50]; // Replace with actual data
      attendanceChart.data.datasets[0].data = newData;
      attendanceChart.update();
    });
  });

  // reports 
  document.addEventListener("DOMContentLoaded", function () {
    // Initialize date range picker
    flatpickr(".date-range-picker", {
        mode: "range",
        dateFormat: "Y-m-d",
        defaultDate: [new Date().setDate(1), new Date()]
    });

    // Load departments and initial data
    loadDepartments();
    loadQuickStats();
    
    // Initialize empty charts
    initCharts();
    
    // Form submission handler
    document.getElementById('attendance-report-form').addEventListener('submit', function(e) {
        e.preventDefault();
        generateReport();
    });
    
    // Reset filters
    document.querySelector('.btn-reset').addEventListener('click', function() {
        document.getElementById('attendance-report-form').reset();
        flatpickr(".date-range-picker").clear();
        loadQuickStats(); // Reload default stats
    });
    
    // Export buttons
    document.getElementById('export-csv').addEventListener('click', exportToCSV);
    document.getElementById('export-pdf').addEventListener('click', exportToPDF);
    document.getElementById('export-print').addEventListener('click', window.print);
});

// Initialize charts
function initCharts() {
    // Trend chart
    window.trendChart = new Chart(
        document.getElementById('attendanceTrendChart'),
        {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Attendance Trend' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        }
    );
    
    // Pie chart
    window.pieChart = new Chart(
        document.getElementById('attendancePieChart'),
        {
            type: 'pie',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Attendance Distribution' },
                    legend: { position: 'right' }
                }
            }
        }
    );
}

// Load quick stats
async function loadQuickStats() {
    try {
        const response = await fetch('../api/routes/reporting.php');
        // const data = await response.json();
        const data = await response.text();
        console.warn(data);
        
        if (data.status === 'success') {
            updateQuickStats(data.stats);
        }
        
    } catch (error) {
        console.error('Error loading quick stats:', error);
    }
}

// Update quick stats display
function updateQuickStats(stats) {
    document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = stats.total_employees;
    document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = stats.present_today;
    document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = stats.absent_today;
    document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = stats.late_arrivals;
}

// Generate report
// async function generateReport() {
//     const form = document.getElementById('attendance-report-form');
//     const dateRange = document.querySelector('.date-range-picker')._flatpickr.selectedDates;
//     const departmentId = form.elements['department'].value;
    
//     const startDate = dateRange[0] ? dateRange[0].toISOString().split('T')[0] : '';
//     const endDate = dateRange[1] ? dateRange[1].toISOString().split('T')[0] : '';
    
//     try {
//         const response = await fetch('../api/routes/reporting.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//             },
//             body: JSON.stringify({
//                 start_date: startDate,
//                 end_date: endDate,
//                 department_id: departmentId
//             })
//         });
        
//         const data = await response.json();
        
//         if (data.status === 'success') {
//             // Update table
//             updateReportTable(data.data);
            
//             // Update charts
//             updateCharts(data.charts);
            
//             // Update quick stats with filtered data
//             updateQuickStats(data.stats);
            
//             // Update summary tab
//             updateSummary(data.data);
//         }
//     } catch (error) {
//         console.error('Error generating report:', error);
//     }
// }

// new report 
async function generateReport() {
    const form = document.getElementById('attendance-report-form');
    const dateRange = document.querySelector('.date-range-picker')._flatpickr.selectedDates;
    const departmentId = form.elements['department'].value;
    
    const startDate = dateRange[0] ? dateRange[0].toISOString().split('T')[0] : '';
    const endDate = dateRange[1] ? dateRange[1].toISOString().split('T')[0] : '';
    
    try {
        const response = await fetch('../api/routes/reporting.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                start_date: startDate,
                end_date: endDate,
                department_id: departmentId
            })
        });
        
        const data = await response.json();
        console.log('API Response:', data); // Add this for debugging
        
        if (data.status === 'success') {
            // Update table
            updateReportTable(data.data);
            
            // Update charts - make sure chartData structure matches what updateCharts expects
            if (data.charts) {
                updateCharts(data.charts);
            } else {
                console.error('No chart data in response');
            }
            
            // Update quick stats with filtered data
            if (data.stats) {
                updateQuickStats(data.stats);
            }
            
            // Update summary tab
            updateSummary(data.data);
        } else {
            console.error('API Error:', data.message);
        }
    } catch (error) {
        console.error('Error generating report:', error);
    }
}

// Update report table
function updateReportTable(data) {
    const tableBody = document.querySelector('.report-table tbody') || 
        document.querySelector('.report-table').createTBody();
    
        tableBody.innerHTML = '';
        
        const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>Sr No</td>
            <td>Name</td>
            <td>Date</td>
            <td>Department</td>
            <td>Time in</td>
            <td>Time out</td>
            <td>Status</td>
            <td>Photo in</td>
            <td>Photo out</td>
            `;
        tableBody.appendChild(tr);

        let i=1;
        data.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${i}</td>
            <td>${row.employee_name}</td>
            <td>${row.Date}</td>
            <td>${row.Department_name}</td>
            <td>${row.Time_in || '-'}</td>
            <td>${row.Time_out || '-'}</td>
            <td>${row.Attendance_type}</td>
            <td>
                ${row.Photo ? `<img src="../api/attendance_pics/${row.Photo}" width="50">` : '-'}
            </td>
            <td>
                ${row.Photo_out ? `<img src="../api/attendance_pics/${row.Photo_out}" width="50">` : '-'}
            </td>
        `;
        tableBody.appendChild(tr);
        i++;
    });
}
       
// Update charts
function updateCharts(chartData) {
    // Debug: Check what data we're receiving
    console.log('Chart Data:', chartData);
    
    // Trend chart - ensure data.trend exists and has Date, present, absent, late properties
    if (chartData.trend && chartData.trend.length > 0) {
        window.trendChart.data.labels = chartData.trend.map(item => {
            // Format date for display (e.g., "Jan 01")
            const date = new Date(item.Date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        window.trendChart.data.datasets = [
            {
                label: 'Present',
                data: chartData.trend.map(item => item.present || 0),
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            },
            {
                label: 'Absent',
                data: chartData.trend.map(item => item.absent || 0),
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            },
            {
                label: 'Late',
                data: chartData.trend.map(item => item.late || 0),
                backgroundColor: 'rgba(255, 206, 86, 0.2)',
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 1
            }
        ];
        window.trendChart.update();
    } else {
        console.error('No trend data available for chart');
    }
    
    // Pie chart - ensure data.distribution exists and has Attendance_type and count properties
    if (chartData.distribution && chartData.distribution.length > 0) {
        window.pieChart.data.labels = chartData.distribution.map(item => item.Attendance_type);
        window.pieChart.data.datasets = [{
            data: chartData.distribution.map(item => item.count || 0),
            backgroundColor: [
                'rgba(75, 192, 192, 0.7)',
                'rgba(255, 99, 132, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(54, 162, 235, 0.7)'
            ],
            borderWidth: 1
        }];
        window.pieChart.update();
    } else {
        console.error('No distribution data available for pie chart');
    }
}

// Update summary tab
function updateSummary(data) {
    const summaryTab = document.getElementById('summary-tab');
    
    // Calculate summary stats
    const presentCount = data.filter(item => item.Attendance_type === 'Present').length;
    const absentCount = data.filter(item => item.Attendance_type === 'Absent').length;
    const lateCount = data.filter(item => item.Attendance_type === 'Late').length;
    const total = data.length;
    
    summaryTab.innerHTML = `
        <div class="summary-stats">
            <h4>Attendance Summary</h4>
            <p>Total Records: ${total}</p>
            <p>Present: ${presentCount} (${total > 0 ? Math.round((presentCount/total)*100) : 0}%)</p>
            <p>Absent: ${absentCount} (${total > 0 ? Math.round((absentCount/total)*100) : 0}%)</p>
            <p>Late: ${lateCount} (${total > 0 ? Math.round((lateCount/total)*100) : 0}%)</p>
        </div>
    `;
}

// Helper function to convert canvas to image
async function canvasToImage(canvas) {
    return new Promise((resolve) => {
        canvas.toBlob((blob) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.readAsDataURL(blob);
        }, 'image/png');
    });
}

// Export functions with charts
function exportToCSV() {
    const table = document.querySelector('.report-table');
    if (!table || table.rows.length <= 1) {
        alert('No data to export');
        return;
    }

    let csv = [];
    // Get headers
    const headers = [];
    for (let i = 0; i < table.rows[0].cells.length; i++) {
        // Skip image columns in CSV
        if (!table.rows[0].cells[i].querySelector('img')) {
            headers.push(`"${table.rows[0].cells[i].textContent.trim()}"`);
        }
    }
    csv.push(headers.join(','));

    // Get data rows
    for (let i = 1; i < table.rows.length; i++) {
        const row = [];
        for (let j = 0; j < table.rows[i].cells.length; j++) {
            // Skip image columns
            if (!table.rows[0].cells[j].querySelector('img')) {
                const content = table.rows[i].cells[j].textContent.trim();
                // Escape quotes and wrap in quotes
                row.push(`"${content.replace(/"/g, '""')}"`);
            }
        }
        csv.push(row.join(','));
    }

    // Download CSV file
    const csvContent = csv.join('\n');
    const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `attendance_report_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
async function exportToPDF() {
    const table = document.querySelector('.report-table');
    if (!table || table.rows.length <= 1) {
        alert('No data to export');
        return;
    }

    // Get chart images
    const trendChartImg = await canvasToImage(document.getElementById('attendanceTrendChart'));
    const pieChartImg = await canvasToImage(document.getElementById('attendancePieChart'));
    
    const dateRange = document.querySelector('.date-range-picker')._flatpickr.selectedDates;
    const startDate = dateRange[0] ? dateRange[0].toISOString().split('T')[0] : '';
    const endDate = dateRange[1] ? dateRange[1].toISOString().split('T')[0] : '';
    
    const htmlContent = `
        <html>
        <head>
            <title>Attendance Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; text-align: center; }
                .report-info { margin-bottom: 20px; text-align: center; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                img { max-width: 100%; }
                .chart-container { margin: 20px 0; page-break-inside: avoid; }
                .chart-row { display: flex; flex-wrap: wrap; gap: 20px; }
                .chart-row > div { flex: 1; min-width: 300px; }
            </style>
        </head>
        <body>
            <h1>Attendance Report</h1>
            <div class="report-info">
                <p><strong>Date Range:</strong> ${startDate} to ${endDate}</p>
                <p><strong>Generated on:</strong> ${new Date().toLocaleString()}</p>
            </div>
            
            <h2>Data Summary</h2>
            ${table.outerHTML}
            
            <h2>Attendance Charts</h2>
            <div class="chart-row">
                <div class="chart-container">
                    <h3>Attendance Trend</h3>
                    <img src="${trendChartImg}" alt="Attendance Trend Chart">
                </div>
                <div class="chart-container">
                    <h3>Attendance Distribution</h3>
                    <img src="${pieChartImg}" alt="Attendance Pie Chart">
                </div>
            </div>
        </body>
        </html>
    `;

    // Send to the PDF export endpoint
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../api/routes/reporting.php?export=pdf';
    form.target = '_blank';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'html';
    input.value = htmlContent;
    form.appendChild(input);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

async function printing() {
    const table = document.querySelector('.report-table');
    if (!table || table.rows.length <= 1) {
        alert('No data to print');
        return;
    }

    // Get chart images
    const trendChartImg = await canvasToImage(document.getElementById('attendanceTrendChart'));
    const pieChartImg = await canvasToImage(document.getElementById('attendancePieChart'));
    
    const dateRange = document.querySelector('.date-range-picker')._flatpickr.selectedDates;
    const startDate = dateRange[0] ? dateRange[0].toISOString().split('T')[0] : '';
    const endDate = dateRange[1] ? dateRange[1].toISOString().split('T')[0] : '';
    
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Attendance Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; text-align: center; }
                .report-info { margin-bottom: 20px; text-align: center; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                img { max-width: 100%; }
                .chart-container { margin: 20px 0; page-break-inside: avoid; }
                .chart-row { display: flex; flex-wrap: wrap; gap: 20px; }
                .chart-row > div { flex: 1; min-width: 300px; }
                @media print {
                    .no-print { display: none; }
                    body { margin: 0; padding: 0; }
                    table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                    .chart-row { display: flex !important; }
                }
            </style>
        </head>
        <body>
            <h1>Attendance Report</h1>
            <div class="report-info">
                <p><strong>Date Range:</strong> ${startDate} to ${endDate}</p>
                <p><strong>Generated on:</strong> ${new Date().toLocaleString()}</p>
            </div>
            
            <h2>Data Summary</h2>
            ${table.outerHTML}
            
            <h2>Attendance Charts</h2>
            <div class="chart-row">
                <div class="chart-container">
                    <h3>Attendance Trend</h3>
                    <img src="${trendChartImg}" alt="Attendance Trend Chart">
                </div>
                <div class="chart-container">
                    <h3>Attendance Distribution</h3>
                    <img src="${pieChartImg}" alt="Attendance Pie Chart">
                </div>
            </div>
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 100);
                    }, 500);
                };
            </script>
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
}
// Update your event listeners to use these functions
document.getElementById('export-csv').addEventListener('click', exportToCSV);
document.getElementById('export-pdf').addEventListener('click', exportToPDF);
document.getElementById('export-print').addEventListener('click', printing);