<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Qpi Schema</title>
</head>
<body>
    <h2>Schema</h2>
    <table>
        <tr>
            <th>Model</th>
            <th>Fields</th>
        </tr>

        @foreach ($info as $model)
            <tr>
                <td>{{$model['name']}}</td>
                <td>
                    <table>
                        <tr>
                            <th>Field</th>
                            <th>Description</th>
                        </tr>
                        @foreach ($model['props'] as $prop => $description)
                            <tr>
                                <td>{{$prop}}</td>
                                <td>{{$description}}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        @endforeach
    </table>
</body>
</html>
