document.addEventListener('DOMContentLoaded', function() {
  // Event delegation - უფრო ეფექტური ერთი listener-ით
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-clear')) {
      const formFloating = e.target.closest('.form-floating');
      const input = formFloating?.querySelector('.form-control');
      
      if (input) {
        input.value = '';
        input.focus();
        
        // Trigger ორივე event-ი
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        
        // Clear button-ის დამალვა
        e.target.style.display = 'none';
      }
    }
  });
  
  // Real-time clear button visibility control
  document.addEventListener('input', function(e) {
    if (e.target.classList.contains('form-control')) {
      const clearBtn = e.target.parentElement.querySelector('.btn-clear');
      if (clearBtn) {
        clearBtn.style.display = e.target.value ? 'block' : 'none';
      }
    }
  });
  
  // Initial state - check all inputs on load
  document.querySelectorAll('.form-floating .form-control').forEach(input => {
    const clearBtn = input.parentElement.querySelector('.btn-clear');
    if (clearBtn && input.value) {
      clearBtn.style.display = 'block';
    }
  });
});
