<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

<title>-</title>

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">

  <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  
</head>

<body class="bg-dark">

  <div class="container">
    <div class="card card-login mx-auto mt-5">
      <div class="card-body">
<form>
          <div class="form-group">
                <div class="form-label-group">
                  <input type="password" id="gwdpasswd" class="form-control" placeholder="密码" required="required" value="" autofocus="autofocus" onkeydown="keysubmitpw(event)">
                  <label for="gwdpasswd">密码</label>
                </div>
          </div>
</form>
<button id="buttonSubmitpw" type="button" class="btn btn-success btn-block">Login</button>
      </div>
    </div>
  </div>

<script>
function keysubmitpw(e){
    var code = (e && e.keyCode) ? e.keyCode : (event ? event.keyCode : 0);
    if(code == 13) {
        if (e && e.preventDefault) e.preventDefault();
        gwdpasswdtext=$('#gwdpasswd').val();
        $.post('auth.php', {gwdpw:gwdpasswdtext}, function(result){ window.location.href="index.php"; });
    }
}

$(function(){
$('#buttonSubmitpw').click(function(){
gwdpasswdtext=$('#gwdpasswd').val()
$.post('auth.php', {gwdpw:gwdpasswdtext}, function(result){ window.location.href="index.php"; })
})
})
</script>

</body>

</html>
