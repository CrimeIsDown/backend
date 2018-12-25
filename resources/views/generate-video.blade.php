<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Convert Audio to Video</title>

    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">

    <!-- Custom styles for this template -->
    <link href="https://getbootstrap.com/docs/4.1/examples/sign-in/signin.css" rel="stylesheet">
</head>
<body class="text-center">
<form class="form-signin" method="post" enctype="multipart/form-data" style="max-width: 30em; padding: 1em">
    @csrf
    <img class="mb-6 py-3 img-fluid" src="/img/video.png" alt="What a video looks like">
    <h1 class="h3 mb-3 font-weight-normal">Generate video</h1>
    @if ($errors->any())
    <div class="form-group">
        @foreach ($errors->all() as $error)
            <div class="alert alert-danger" role="alert">
            {{ $error }}
            </div>
        @endforeach
    </div>
    @endif
    <div class="form-group">
        <label for="inputTitle" class="sr-only">Title</label>
        <input type="text" name="title" id="inputTitle" class="form-control" placeholder="Title (optional)" maxlength="40" style="padding: .375rem .75rem">
    </div>
    <div class="form-group">
        <div class="input-group mb-3" style="font-size: 16px; text-align: left;">
            <div class="input-group-prepend">
                <span class="input-group-text" id="inputGroupFileAddon01">Upload</span>
            </div>
            <div class="custom-file">
                <input type="file" name="audioFile" class="custom-file-input" id="inputFile" aria-describedby="inputFile">
                <label class="custom-file-label" for="inputFile">Choose audio file</label>
            </div>
        </div>
    </div>
    <button class="btn btn-lg btn-primary btn-block" type="submit">Convert</button>
</form>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
<script type="text/javascript">
    $("input[type=file]").change(function () {
        var fieldVal = $(this).val();

        // Change the node's value by removing the fake path (Chrome)
        fieldVal = fieldVal.replace("C:\\fakepath\\", "");

        if (fieldVal != undefined || fieldVal != "") {
            $(this).next(".custom-file-label").attr('data-content', fieldVal);
            $(this).next(".custom-file-label").text(fieldVal);
        }

    });
</script>
</body>
</html>
