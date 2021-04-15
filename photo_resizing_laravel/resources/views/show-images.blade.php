<html>
<body>
    @foreach($files as $file)
    <img src="{{ asset('storage/'.$file)}}" style="width: 150px">
    @endforeach

</body>

</html>
