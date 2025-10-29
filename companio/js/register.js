function setUserType(type) {
  const roleInput = document.getElementById('role');
  const tFields = document.getElementById('touristFields');
  const gFields = document.getElementById('guideFields');
  const buttons = document.querySelectorAll('.toggle-btn');
  buttons.forEach(b => b.classList.remove('active'));
  if (type === 'tourist') {
    buttons[0].classList.add('active');
    roleInput.value = 'tourist';
    tFields.classList.remove('hidden');
    gFields.classList.add('hidden');
  } else {
    buttons[1].classList.add('active');
    roleInput.value = 'guide';
    tFields.classList.add('hidden');
    gFields.classList.remove('hidden');
  }
}

// Toggle interest tag visual when clicking label
document.addEventListener('click', function(e){
  if (e.target && e.target.closest('.interest-tag')) {
    const label = e.target.closest('.interest-tag');
    const cb = label.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
    label.classList.toggle('selected', cb.checked);
  }
});
