<script>
(function () {
  const mainImg = document.getElementById('pv-main-img');
  const thumbs  = document.querySelectorAll('.pv-thumb');
 
  if (!mainImg || !thumbs.length) return;
 
  thumbs.forEach(btn => {
    btn.addEventListener('click', function () {
      const newSrc = this.dataset.src;
      if (mainImg.src.endsWith(newSrc)) return; // already showing
 
      // Fade out → swap → fade in
      mainImg.classList.add('pv-switching');
      setTimeout(() => {
        mainImg.src = newSrc;
        mainImg.classList.remove('pv-switching');
      }, 220);
 
      // Update active state
      thumbs.forEach(t => t.classList.remove('active'));
      this.classList.add('active');
    });
  });
})();
</script>
