<h2>Import Tourist Excel</h2>

@if(session('success'))
    <p style="color:green">{{ session('success') }}</p>
@endif

<form method="POST" action="{{ url('/tourist/import') }}" enctype="multipart/form-data">
    @csrf
    <input type="file" name="file" required>
    <br><br>
    <button type="submit">Upload Excel</button>
</form>
