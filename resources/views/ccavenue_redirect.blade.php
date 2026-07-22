{{-- resources/views/ccavenue_redirect.blade.php --}}
<form id="ccavenueForm" method="post" action="https://secure.ccavenue.ae/transaction/transaction.do?command=initiateTransaction">
  <input type="hidden" name="encRequest" value="{{ $encRequest }}">
  <input type="hidden" name="access_code" value="{{ $accessCode }}">
</form>

<script>
  document.getElementById('ccavenueForm').submit();
</script>
