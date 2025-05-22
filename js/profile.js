document.addEventListener('DOMContentLoaded', function() {
    // Load profile data
    fetchProfileData();
    
    // Form submission handler
    document.getElementById('profile-form').addEventListener('submit', function(e) {
        e.preventDefault();
        updateProfile();
    });
});

function fetchProfileData() {
    fetch('../api/routes/profile.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                renderProfileForm(data.profile);
            } else {
                alert('Error loading profile: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load profile data');
        });
}

function renderProfileForm(profile) {
    // Set common fields
    document.getElementById('id').value = profile.id || '';
    
    // Get the dynamic fields container
    const dynamicFields = document.getElementById('dynamic-fields');
    dynamicFields.innerHTML = '';
    
    // Create fields based on user role
    let fieldsHTML = '';
    
    switch (profile.role) {
        case 'admin':
            fieldsHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email: <i class="fas fa-envelope"></i></label>
                        <input type="email" id="email" name="email" value="${profile.Email || ''}" >
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password: <i class="fas fa-lock"></i></label>
                        <input type="password" id="password" name="password" >
                    </div>
                </div>
            `;
            break;
            
        case 'member':
            fieldsHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label for="company_name">Company Name: <i class="fas fa-building"></i></label>
                        <input type="text" id="company_name" name="company_name" value="${profile.Name || ''}" >
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email: <i class="fas fa-envelope"></i></label>
                        <input type="email" id="email" name="email" value="${profile.Email || ''}" >
                    </div>
                    <div class="form-group">
                        <label for="number">Contact Number: <i class="fas fa-phone"></i></label>
                        <input type="text" id="number" name="number" value="${profile.Number || ''}" >
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password: <i class="fas fa-lock"></i></label>
                        <input type="password" id="password" name="password" >
                    </div>
                </div>
            `;
            break;
            
        case 'employee':
            fieldsHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Name: <i class="fas fa-user"></i></label>
                        <input type="text" id="name" name="name" value="${profile.Name || ''}" readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="number">Contact Number: <i class="fas fa-phone"></i></label>
                        <input type="text" id="number" name="number" value="${profile.Number || ''}" >
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="number">Email: <i class="fas fa-phone"></i></label>
                        <input type="email" id="email" name="email" value="${profile.email || ''}" >
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="job_in_timing">Job in timing: <i class="fas fa-phone"></i></label>
                        <input type="email" id="job_in_timing" name="job_in_timing" value="${profile.job_in_timing || ''}" >
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="job_out_timing">Job out timing: <i class="fas fa-phone"></i></label>
                        <input type="email" id="job_out_timing" name="job_out_timing" value="${profile.job_out_timing || ''}" >
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password: <i class="fas fa-lock"></i></label>
                        <input type="password" id="password" name="password" >
                    </div>
                </div>
            `;
            break;
    }
    
    dynamicFields.innerHTML = fieldsHTML;
}

async function updateProfile() {
    const role = document.getElementById('profile-form').getAttribute('data-role');
    const formData = {};
    const formElements = document.getElementById('profile-form').elements;
    
    // Collect form data based on role
    for (let element of formElements) {
        if (element.name && element.type !== 'submit') {
            formData[element.name] = element.value;
        }
    }
    
    try {
        const response = await fetch('../api/routes/profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });
        const data = await response.json();
        // const data = await response.text();
        // console.warn(data);
        

        if (data.status === 'success') {
            alert('Profile updated successfully');
            await fetchProfileData(); // Refresh data
        } else {
            alert('Error updating profile: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to update profile');
    }
}