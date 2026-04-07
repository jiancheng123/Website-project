
function showForm(formId) {
      document.getElementById('loginBox').classList.remove('active');
      document.getElementById('registerBox').classList.remove('active');
      document.getElementById(formId).classList.add('active');
    }

function showForm(formId) {
    document.querySelectorAll('.form-box').forEach(form => {
        form.classList.remove('active');
    });
    document.getElementById(formId).classList.add('active');
}

// Handle Contact Form Validation
function validateForm() {
  const name = document.getElementById('name').value.trim();
  const email = document.getElementById('email').value.trim();
  const message = document.getElementById('message').value.trim();
  const error = document.getElementById('formError');

  // Reset previous error
  error.textContent = "";

  // Check for empty fields
  if (!name || !email || !message) {
    error.textContent = "Please fill in all fields.";
    return false;
  }

  // Validate email format
  const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;
  if (!emailPattern.test(email)) {
    error.textContent = "Please enter a valid email.";
    return false;
  }

  return true;
}



