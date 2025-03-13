<footer class="text-center mt-5 p-3" style="background-color: #D16301; color: white;">
  <p>© <?= date('Y'); ?> Credinowe. Todos os direitos reservados.</p>
</footer>
</body>

</html>

<script>
  const refreshInterval = 300000; // 2 minutos
  setTimeout(() => {
    window.location.reload();
  }, refreshInterval);
</script>