<?php
require_once('auth.php');
if (empty($auth)) {
    header('Location: login.php');
    exit;
}
$de_GWDconf = gwd_read_conf_object();
$pageAlias = isset($de_GWDconf->address->alias) && is_scalar($de_GWDconf->address->alias) ? trim((string)$de_GWDconf->address->alias) : '';
$pageTitle = ($pageAlias !== '') ? $pageAlias : '寒月';
$checkJellyfin = (isset($de_GWDconf->app->jellyfin) && is_scalar($de_GWDconf->app->jellyfin)) ? (string)$de_GWDconf->app->jellyfin : null;
$checkBitwarden = (isset($de_GWDconf->app->bitwarden) && is_scalar($de_GWDconf->app->bitwarden)) ? (string)$de_GWDconf->app->bitwarden : null;
$checkNFS = file_exists('/etc/auto.nfs');
$checkDocker = file_exists('/usr/bin/docker');
?>
<!DOCTYPE html>
<html>

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

<title><?php echo gwd_h($pageTitle); ?></title>

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">

  <link href="favicon.ico" rel="icon" type="image/x-icon" />

  <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <meta name="csrf-token" content="<?php echo gwd_h($csrfToken ?? ''); ?>">
  <script src="js/gwd-csrf.js"></script>

</head>

<body id="page-top" class="sidebar-toggled">
  <nav class="navbar navbar-expand navbar-dark bg-dark static-top">

    <a class="navbar-brand mr-1" href="index.php">寒月</a>
    <button class="btn btn-sm btn-outline-light mx-3" data-toggle="modal" data-target="#markThis">备注本机</button>
    
    <button class="btn btn-link btn-sm text-white order-1 order-sm-0" id="sidebarToggle" href="#">
      <i class="fas fa-bars"></i>
    </button>
<span class="float-right badge text-info"><?php passthru('sudo /opt/de_GWD/ui-checkEditionARM');?></span>

    <!-- Navbar Search -->
    <form class="d-none d-md-inline-block form-inline ml-auto mr-0 mr-md-3 my-2 my-md-0">
    </form>

    <!-- Navbar -->
    <ul class="nav navbar-nav ml-auto ml-md-0">
      <li class="nav-item no-arrow mx-1" style="display:<?php if ($checkJellyfin === 'installed') echo 'block'; else echo 'none';?>">
        <a class="nav-link" href="javascript:void(0)" onclick="window.open(location.protocol+'//'+location.hostname+':8097')">
          <i class="fab fa-youtube"></i>
          <span>Jellyfin</span>
        </a>
      </li>

      <li class="nav-item no-arrow mx-1" style="display:<?php if ($checkBitwarden === 'installed') echo 'block'; else echo 'none';?>">
        <a class="nav-link" href="javascript:void(0)" onclick="window.open(location.protocol+'//'+location.hostname+':8099')">
          <i class="fas fa-shield-alt"></i>
          <span>Bitwarden</span>
        </a>
      </li>
      
      <li class="nav-item no-arrow mx-1">
        <a class="nav-link" href="/admin/" target="_blank">
          <i class="fab fa-raspberry-pi"></i>
          <span>Pi-Hole</span>
        </a>
      </li>
    </ul>

  </nav>

  <div id="wrapper">

    <!-- Sidebar -->
    <ul class="sidebar navbar-nav toggled">
      <li class="nav-item">
        <a class="nav-link" href="index.php">
          <i class="fas fa-tachometer-alt"></i>
          <span>概览</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="forward.php">
          <i class="fas fa-project-diagram"></i>
          <span>中转</span></a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="ddns.php">
          <i class="fas fa-ethernet"></i>
          <span>DDNS & Wireguard</span></a>
      </li>
      <li class="nav-item active">
        <a class="nav-link" href="app.php">
          <i class="fab fa-app-store-ios"></i>
          <span>应用</span></a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="nodes.php">
          <i class="fas fa-stream"></i>
          <span>节点管理</span></a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="update.php">
          <i class="fas fa-arrow-alt-circle-up"></i>
          <span>更新</span></a>
      </li>
      <li class="nav-item">
        <a id="buttonLogout" class="nav-link" href="javascript:void(0)">
          <i class="fas fa-sign-out-alt"></i>
          <span>注销</span></a>
      </li>
    </ul>


    <div id="content-wrapper" class="mx-auto" style="max-width: 1600px;">

      <div class="container-fluid">

        <!-- Breadcrumbs-->
        <ol class="breadcrumb">
          <li class="breadcrumb-item">
            <a href="index.php">概览</a>
          </li>
          <li class="breadcrumb-item active">应用</li>
        </ol>


<!-- Modal -->
<div id="markThis" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="markThisLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="markThisLabel" class="modal-title">备注本机</h5>
      </div>
      <div class="modal-body">
        <input id="markName" type="text" class="form-control" placeholder="备注名" required="required" value="<?php echo gwd_h($de_GWDconf->address->alias ?? ''); ?>">
      </div>
      <div class="modal-footer">
        <button id="buttonMarkThis" type="button" class="btn btn-outline-dark btn-sm">应用</button>
      </div>
    </div>
  </div>
</div>

        <!-- Page Content -->   
        <div class="card mb-3">
          <div class="card-header">
            <i class="fas fa-folder"></i>
            NFS挂载
<span id="NFSbutton" class="float-right mt-n1 mb-n2 ml-4" style="display:<?php if ($checkNFS === true) echo 'block'; else echo 'none';?>">
<button id="buttonNFSAdd" type="button" class="btn btn-secondary btn-sm mt-1" style="border-Radius: 0px;">新增</button>
</span>
<span id="NFSswitch" class="float-right mt-n1 mb-n2 ml-4" style="display:<?php if ($checkNFS === true) echo 'none'; else echo 'block';?>">
<button id="buttonNFSswitch" type="button" class="btn btn-secondary btn-sm mt-1" style="border-Radius: 0px;">展开</button>
</span>
<span class="float-right mt-n1 mb-n2">
<button id="buttonNFSinstall" type="button" class="btn <?php if ($checkNFS === true) echo 'btn-outline-success'; else echo 'btn-outline-secondary'; ?> btn-sm mt-1" style="border-Radius: 0px;">install</button>
</span>
          </div>

          <div class="card-body" id="NFSbody" style="display:<?php if ($checkNFS === true) echo 'block'; else echo 'none';?>">
<?php
$rawNFS = shell_exec("sudo nfsstat -m | sed '/Flags/d' | sed '/^\s*$/d'");
$arrayNFS = !empty($rawNFS) ? explode("\n", trim($rawNFS)) : [];
$NFSnum = count($arrayNFS);
for( $i=0; $i<$NFSnum; $i++){
  $NFSstr1 = explode(" ",$arrayNFS[$i]);
  $NFSstr2 = explode(":",$NFSstr1[2] ?? '');
  $NFSpoint = gwd_h($NFSstr1[0] ?? '');
  $NFSserver = gwd_h($NFSstr2[0] ?? '');
  $NFSaddress = gwd_h($NFSstr2[1] ?? '');
print <<<EOT
<div class="form-row">
  <div class="col-md-4 input-group my-2">
    <div class="input-group-prepend">
      <span class="input-group-text">挂载点</span>
    </div>
      <input id="NFSpoint$i" type="text" class="form-control" value="$NFSpoint" READONLY>
  </div>

  <div class="col-md-8 input-group my-2">
    <div class="input-group-prepend">
      <span class="input-group-text justify-content-center">NFS服务器地址</span>
    </div>
      <input id="NFSserver$i" type="text" class="form-control" style="max-width: 160px;" value="$NFSserver" READONLY>
    <div class="input-group-prepend input-group-append">
  </div>
    <span id="NFSaddress$i" class="input-group-text form-control">$NFSaddress</span>
    <button type="button" class="btn btn-outline-danger btn-sm text-right ml-2" style="border-Radius: 0px;" onclick="NFSdel(this)">删除</button>
  </div>
</div>
EOT;
}
?>

<!-- Modal -->
<div id="NFSmodal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-body">
        <input id="NFSpath" type="text" class="form-control" required="required" value="" READONLY>

        <ul id="NFSlist" class="list-group mt-3" style="max-height:320px;overflow:scroll">
        </ul>

      </div>
      <input id="NFSmodalID" type="hidden" value="">
      <div class="modal-footer">
        <button id="confirmNFSaddr" type="button" class="btn btn-outline-dark btn-sm">应用</button>
      </div>
    </div>
  </div>
</div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">
            <i class="fab fa-docker"></i>
            Docker
<span id="dockerSwitch" class="float-right mt-n1 mb-n2 ml-4" style="display:<?php if ($checkDocker === true) echo 'none'; else echo 'block';?>">
<button id="buttonDockerSwitch" type="button" class="btn btn-secondary btn-sm mt-1" style="border-Radius: 0px;">展开</button>
</span>
<span class="float-right mt-n1 mb-n2">
<button id="buttonDockerInstall" type="button" class="btn <?php if ($checkDocker === true) echo 'btn-outline-success'; else echo 'btn-outline-secondary'; ?> btn-sm mt-1" style="border-Radius: 0px;">install</button>
</span>
          </div>

          <div class="card-body" id="dockerBody" style="display:<?php if ($checkDocker === true) echo 'block'; else echo 'none';?>">
    <div class="form-row">
      <div class="col-md-6 input-group my-2">
        <div class="input-group-prepend">
          <button id="buttonBWinstall" type="button" class="btn <?php if ($checkBitwarden === 'installed') echo 'btn-success'; else echo 'btn-outline-secondary'; ?>"><?php if ($checkBitwarden === 'installed') echo 'Bitwarden'; else echo 'install Bitwarden';?></button>
        </div>
        <div class="input-group-prepend">
          <button id="bitwardenBackup" type="button" class="btn btn-outline-secondary">
            <span id="bitwardenBackupLoading"></span>
            备份下载
          </button>
        </div>
          <div class="custom-file">
            <input id="bitwardenRestoreFile" type="file" class="custom-file-input">
            <label id="bitwardenRestoreFileLabel" class="custom-file-label" for="bitwardenRestoreFile">...</label>
          </div>
        <div class="input-group-append">
          <button id="buttonBitwardenRestore" type="button" class="btn btn-secondary">
            <span id="buttonBitwardenRestoreLoading"></span>
            <span>上传恢复</span>
          </button>
        </div>
      </div>

      <div class="col-md-6 input-group my-2">
        <div class="input-group-prepend">
          <button id="buttonJFinstall" type="button" class="btn <?php if ($checkJellyfin === 'installed') echo 'btn-success'; else echo 'btn-outline-secondary'; ?>"><?php if ($checkJellyfin === 'installed') echo 'Jellyfin'; else echo 'install Jellyfin';?></button>
        </div>
        <div class="input-group-prepend">
          <button id="JellyfinBackup" type="button" class="btn btn-outline-secondary">
            <span id="JellyfinBackupLoading"></span>
            备份下载
          </button>
        </div>
          <div class="custom-file">
            <input id="JellyfinRestoreFile" type="file" class="custom-file-input">
            <label id="JellyfinRestoreFileLabel" class="custom-file-label" for="JellyfinRestoreFile">...</label>
          </div>
        <div class="input-group-append">
          <button id="buttonJellyfinRestore" type="button" class="btn btn-secondary">
            <span id="buttonJellyfinRestoreLoading"></span>
            <span>上传恢复</span>
          </button>
        </div>
      </div>
    </div>

          </div>
        </div>
        <!-- Page Content -->
      </div>
      

      <!-- /.container-fluid -->

      <!-- Sticky Footer -->
      <footer class="sticky-footer">
        <div class="container my-auto">
          <div class="copyright text-center my-auto">
            <span>Copyright © de_GWD by JacyL4 2017 ~ 2025</span>
          </div>
        </div>
      </footer>

    </div>
    <!-- /.content-wrapper -->

  </div>
  <!-- /#wrapper -->
<script>
function NFSdel(btn){
var $row = $(btn).closest('.form-row')
var NFSpoint = $row.find("input[id^='NFSpoint']").val()
if (!NFSpoint) {
  $row.remove()
  return
}
$.post('./act/offNFS.php', {NFSpoint:NFSpoint}, function(result){
  $row.remove()
}).fail(function(xhr){
  alert($.trim(xhr.responseText) || '卸载NFS失败')
})
}


$(function(){
$('#buttonLogout').click(function(){
$.post('auth.php', {logout:'true'}).always(function(){window.location.href="login.php"})
})

$('#buttonMarkThis').click(function(){
markNametxt=$('#markName').val()
$.post('./act/markThis.php', {markName:markNametxt}, function(result){window.location.reload()})
})

$('#buttonNFSinstall').click(function(){
$.post('./act/installNFS.php', function(){})
var win = window.open('/ttyd', 'popupWindow', 'width=900, height=900, scrollbars=yes')
if (!win) {
    alert('弹出窗口被拦截，请允许弹窗后重试');
    return;
}
var timer = setInterval(function() { 
    if(!win || win.closed) {
        clearInterval(timer);
        $.post('./act/installZ.php', function(result){window.location.reload()})
    }
}, 300);
})

$('#buttonNFSswitch').click(function(){
$('#NFSswitch').css('display', 'none'); 
$('#NFSbutton').css('display', 'block'); 
$('#NFSbody').css('display', 'block'); 
})

$('#buttonNFSAdd').click(function(){
  var i = $("#NFSbody .form-row").length
  $('#NFSbody').append(`
    <div class="form-row">
      <div class="col-md-4 input-group my-2">
        <div class="input-group-prepend">
          <span class="input-group-text">挂载点</span>
        </div>
          <input id="NFSpoint${i}" type="text" class="form-control" value="" READONLY>
      </div>

      <div class="col-md-8 input-group my-2">
        <div class="input-group-prepend">
          <span class="input-group-text justify-content-center">NFS服务器地址</span>
        </div>
          <input id="NFSserver${i}" type="text" class="form-control" style="max-width: 160px;" value="">
        <div class="input-group-prepend input-group-append">
          <button id="NFSlink${i}" type="button" class="btn btn-outline-secondary btn-sm" style="border-Radius: 0px;">连接</button>
        </div>
          <span id="NFSaddress${i}" class="input-group-text form-control"></span>
          <button type="button" class="btn btn-outline-secondary btn-sm text-right ml-2" style="border-Radius: 0px;" onclick="NFSdel(this)">删除</button>
      </div>
    </div>
                          `)

  $('#NFSlink'+i).click(function(){
  var NFSserver = $('#NFSserver'+i).val()
  $("#NFSmodal").modal('show')
  $(":hidden#NFSmodalID").val(i)
  $("#NFSpath").val("选择路径并应用")
  $("li[id^='NFSlistLine']").remove()
  $.post('./act/getNFSlist.php', {NFSserver:NFSserver}, function(data){
  var lines = data.split('\n');
  $("li[id^='NFSlistLine']").remove()
  for( let j = 0; j<lines.length; j++){
    let NFSlistLine = $.trim(lines[j]);
    if (!NFSlistLine) continue;
    $('<li>', {id: 'NFSlistLine' + j, type: 'button', 'class': 'list-group-item'}).text(NFSlistLine).appendTo('#NFSlist');
    $('#NFSlistLine'+j).click(function(){
      $("#NFSpath").val(NFSlistLine.split(/\s+/)[0])
  })
  }
  }).fail(function(xhr){
    $("li[id^='NFSlistLine']").remove()
    $('<li>', {id: 'NFSlistLine0', 'class': 'list-group-item text-danger'}).text($.trim(xhr.responseText) || '获取NFS共享列表失败').appendTo('#NFSlist')
  })
  })
})

$('#confirmNFSaddr').click(function(){
  var NFSmodalID =  $(":hidden#NFSmodalID").val()
  var selectedPath = $.trim($('#NFSpath').val() || '')
  // Derive a safe mount point name: last non-empty path segment, restricted to [A-Za-z0-9._-]
  var segs = selectedPath.split('/').filter(function(s){ return s !== '' })
  var mntName = (segs.length ? segs[segs.length - 1] : '').replace(/[^A-Za-z0-9._-]/g, '_').replace(/^\.+/, '')
  if (!selectedPath || selectedPath.charAt(0) !== '/' || !mntName) {
    alert('请选择有效的NFS共享路径')
    return
  }
  $("#NFSaddress"+NFSmodalID).text(selectedPath)
  $("#NFSpoint"+NFSmodalID).val("/mnt/"+mntName)
var NFSlist = []
$('#NFSbody .form-row').each(function(){
    var NFSpoint = $(this).find("input[id^='NFSpoint']").val()
    var NFSserver = $(this).find("input[id^='NFSserver']").val()
    var NFSaddress = $(this).find("span[id^='NFSaddress']").text()
    if ( NFSpoint !== '' && NFSserver !== '' && NFSaddress !== '') {
    NFSlist.push({NFSpoint, NFSserver, NFSaddress})
    }
})
$.post('./act/onNFS.php', {NFSlist:NFSlist}, function(result){
  if (/^skipped \d+/.test($.trim(result || ''))) {
    alert('已保存，但有 ' + $.trim(result).split(' ')[1] + ' 条无效的NFS挂载被跳过')
  }
  window.location.reload()
}).fail(function(xhr){
  alert($.trim(xhr.responseText) || 'NFS挂载保存失败')
})
})

$('#buttonDockerInstall').click(function(){
$.post('./act/installDocker.php', function(){})
var win = window.open('/ttyd', 'popupWindow', 'width=900, height=900, scrollbars=yes')
if (!win) {
    alert('弹出窗口被拦截，请允许弹窗后重试');
    return;
}
var timer = setInterval(function() { 
    if(!win || win.closed) {
        clearInterval(timer);
        $.post('./act/installZ.php', function(result){window.location.reload()})
    }
}, 300);
})

$('#buttonDockerSwitch').click(function(){
$('#dockerSwitch').css('display', 'none'); 
$('#dockerBody').css('display', 'block'); 
})

$('#buttonBWinstall').click(function(){
$.post('./act/installBitwarden.php', function(){})
var win = window.open('/ttyd', 'popupWindow', 'width=900, height=900, scrollbars=yes')
if (!win) {
    alert('弹出窗口被拦截，请允许弹窗后重试');
    return;
}
var timer = setInterval(function() { 
    if(!win || win.closed) {
        clearInterval(timer);
        $.post('./act/installZ.php', function(result){window.location.reload()})
    }
}, 300);
})

$('#bitwardenBackup').click(function(){
$("#bitwardenBackupLoading").attr("class", "spinner-border spinner-border-sm")
$.post('./act/backupBitwarden.php', function(result){
  $("#bitwardenBackupLoading").removeClass()
  window.location.href = "./act/backupDownload.php?type=bitwarden"
}).fail(function(xhr){
  $("#bitwardenBackupLoading").removeClass()
  alert($.trim(xhr.responseText) || 'Bitwarden备份失败')
})
})

$('#bitwardenRestoreFile').on('change',function(){
  let fileName = $(this).val().split('\\').pop()
  $(this).siblings('#bitwardenRestoreFileLabel').addClass('selected').text(fileName)
  if( fileName != "Bitwarden_bak.zip" ){
  alert("文件选择错误，备份文件名为 Bitwarden_bak.zip ")
  }
})

$('#buttonBitwardenRestore').click(function(){
$("#buttonBitwardenRestoreLoading").attr("class", "spinner-border spinner-border-sm")
var form_data = new FormData()
var file_data = $('#bitwardenRestoreFile').prop('files')[0]
form_data.append('file', file_data)
$.ajax({
        url: './act/restoreBitwarden.php',
        cache: false,
        contentType: false,
        processData: false,
        data: form_data,
        type: 'post',
        success: function(result){
          $("#buttonBitwardenRestoreLoading").removeClass()
        },
        error: function(xhr){
          $("#buttonBitwardenRestoreLoading").removeClass()
          alert($.trim(xhr.responseText) || 'Bitwarden恢复上传失败')
        }
      })
})

$('#buttonJFinstall').click(function(){
$.post('./act/installJellyfin.php', function(){});
var win = window.open('/ttyd', 'popupWindow', 'width=900, height=900, scrollbars=yes')
if (!win) {
    alert('弹出窗口被拦截，请允许弹窗后重试');
    return;
}
var timer = setInterval(function() { 
    if(!win || win.closed) {
        clearInterval(timer);
        $.post('./act/installZ.php', function(result){window.location.reload()})
    }
}, 300);
})

$('#JellyfinBackup').click(function(){
$("#JellyfinBackupLoading").attr("class", "spinner-border spinner-border-sm")
$.post('./act/backupJellyfin.php', function(result){
  $("#JellyfinBackupLoading").removeClass()
  window.location.href = "./act/backupDownload.php?type=jellyfin"
}).fail(function(xhr){
  $("#JellyfinBackupLoading").removeClass()
  alert($.trim(xhr.responseText) || 'Jellyfin备份失败')
})
})

$('#JellyfinRestoreFile').on('change',function(){
  let fileName = $(this).val().split('\\').pop()
  $(this).siblings('#JellyfinRestoreFileLabel').addClass('selected').text(fileName)
  if( fileName != "Jellyfin_bak.zip" ){
  alert("文件选择错误，备份文件名为 Jellyfin_bak.zip ")
  }
})

$('#buttonJellyfinRestore').click(function(){
$("#buttonJellyfinRestoreLoading").attr("class", "spinner-border spinner-border-sm")
var form_data = new FormData()
var file_data = $('#JellyfinRestoreFile').prop('files')[0]
form_data.append('file', file_data)
$.ajax({
        url: './act/restoreJellyfin.php',
        cache: false,
        contentType: false,
        processData: false,
        data: form_data,
        type: 'post',
        success: function(result){
          $("#buttonJellyfinRestoreLoading").removeClass()
        },
        error: function(xhr){
          $("#buttonJellyfinRestoreLoading").removeClass()
          alert($.trim(xhr.responseText) || 'Jellyfin恢复上传失败')
        }
      })
})

})
</script>

  <!-- Custom scripts for all pages-->
  <script src="js/sb-admin.min.js"></script>
  
</body>

</html>
