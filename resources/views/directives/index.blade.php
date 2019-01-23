<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chicago Police Department Directive Changes</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.12.1/bootstrap-table.min.css" integrity="sha256-UP+6xezmmsC027u61a8Ks+Xnp+TftBfw4PcrNyPXC40=" crossorigin="anonymous" />
    <style>
        .table td {
            padding: 0.25rem;
        }
        .directive-title {
            width: 40%;
        }
        .directive-date {
            width: 15%;
        }
        .directive-rescinds {
            width: 15%;
        }
        .directive-category {
            width: 35%;
        }
        #directiveViewer iframe {
            width: 100%;
            height: 70vh;
            border: none;
        }
        #directiveViewer form input {
            margin: 0 0.25em;
            padding: 0.4em;
            width: 50%;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="col-lg-12">
        <h1>Chicago Police Department Directive Changes</h1>
        <h4>Diffs on all CPD directives updated since 2015. We check weekly for new updates. A project of <a href="https://crimeisdown.com/">CrimeIsDown.com</a>. View the raw diffs on <a href="https://github.com/CrimeIsDown/cpd-directives">GitHub</a>.</h4>
        <table id="directives" class="table" data-pagination="true" data-search="true" data-toggle="table">
            <thead>
            <tr>
                <th class="directive-title" data-sortable="true">Directive Title</th>
                <th class="directive-date" data-sortable="true" data-sorter="dateSorter">Issue Date</th>
                <th class="directive-date" data-sortable="true" data-sorter="dateSorter">Effective Date</th>
                <th class="directive-rescinds" data-sortable="true">Rescinds</th>
                <th class="directive-category" data-sortable="true">Index Category</th>
            </tr>
            </thead>
            <tbody>
                @foreach($directives as $directive)
                    <tr>
                        <td class="directive-title" >
                            <a href="{{ '/directives/diff/'.$directive->path }}">{!! $directive->title !!}</a>
                        </td>
                        <td class="directive-date" >{{ $directive->issue_date }}</td>
                        <td class="directive-date" >{{ $directive->effective_date }}</td>
                        <td class="directive-rescinds" >{{ $directive->rescinds }}</td>
                        <td class="directive-category" >{{ $directive->index_category }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="directiveViewer" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Directive Name</h4>
            </div>
            <div class="modal-body">
                <iframe src="#">
                    <p>Your browser does not support iframes. <a href="#">View the directive here.</a></p>
                </iframe>
            </div>
            <div class="modal-footer">
                <form class="form-inline">
                    <label for="link">Shareable link</label>
                    <input name="link" type="text" class="form-control" readonly>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.12.1/bootstrap-table.min.js" integrity="sha256-l+jZldmOASkIPelan4NKyAFD+oGNqPulrVQwsjxuCXc=" crossorigin="anonymous"></script>
<script>
    $('#directives').bootstrapTable();

    $('#directives td > a').click(function (event) {
        event.preventDefault();
        openDirective($(this).attr('href'), $(this).text());
    });

    if (window.location.hash) {
        var path = window.location.hash.substring(1);
        var title = $('#directives td > a[href*="' + path + '"]').text();
        openDirective('{{ url('directives/diff') }}' + path, title);
        window.location.hash = '';
    }

    function dateSorter(a, b) {
        a = new Date(a);
        b = new Date(b);
        if (a > b) return 1;
        if (a < b) return -1;
        return 0;
    }

    function openDirective(path, title) {
        $('#directiveViewer h4.modal-title').text(title);
        $('#directiveViewer iframe a').attr('href', path);
        $('#directiveViewer iframe').attr('src', path);
        $('#directiveViewer').modal();
        $('#directiveViewer input[type="text"]').val(window.location + '#' + path.substring(40));
        ga('send', 'event', 'Directive', 'open', title);
    }

    $('#directiveViewer form input').focus(function (event) {
        $(this).select();
        try {
            document.execCommand('copy');
        } catch (e) {
            // not supported
        }
    });

    // GOOGLE ANALYTICS START
    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

    ga('create', 'UA-30674963-12', 'auto');
    ga('send', 'pageview');
    // GOOGLE ANALYTICS END
</script>
</body>
</html>
