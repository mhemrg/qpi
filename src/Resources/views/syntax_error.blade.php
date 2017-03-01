<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Qpi Syntax Error</title>
    <style media="screen">
      .error {
        color: white;
        background: #fb2929;
      }
      .pointer {
        background: #fff1ac;
      }
    </style>
  </head>
  <body>
    <pre>
    @php
      $source = explode("\n", $source);

      $output = [];
      for ($i=0; $i < count($source); $i++) {
        $line = $source[$i];

        if($i === $row - 1) {
          $line = "<div class='error'>{$line}</div>";

          $pointer = array_fill(0, $col - 1, '&#32;');
          array_push($pointer, '^');
          $pointer = implode("", $pointer);
          $line .= "<div class='pointer'>{$pointer}</div>";
        }

        array_push($output, $line);
      }
      echo implode("\n", $output);
    @endphp
    </pre>
  </body>
</html>
