<?php require_once('auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<!DOCTYPE html>
<html>

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="de_GWD">
  <meta name="author" content="JacyL4">

<title>寒月</title>

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">
  <link href="css/animation.css" rel="stylesheet">

  <link href="favicon.ico" rel="icon" type="image/x-icon" />

  <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

</head>

<body id="page-top" class="sidebar-toggled">
<?php $de_GWDconf = json_decode(file_get_contents('/opt/de_GWD/0conf')); ?>
<?php $DoG = $de_GWDconf->dns->dog ?? ''; ?>

<?php $checkJellyfin = $de_GWDconf->app->jellyfin ?? ''; ?>
<?php $checkBitwarden = $de_GWDconf->app->bitwarden ?? ''; ?>

<?php $v2nodes = (isset($de_GWDconf->v2node) && is_array($de_GWDconf->v2node)) ? $de_GWDconf->v2node : []; ?>
  <nav class="navbar navbar-expand navbar-dark bg-dark static-top">

    <a class="navbar-brand mr-1" href="index.php">寒月</a>
    <button class="btn btn-sm btn-outline-light mx-3" data-toggle="modal" data-target="#markThis">备注本机</button>

    <button id="sidebarToggle" class="btn btn-link btn-sm text-white order-1 order-sm-0" href="#">
      <i class="fas fa-bars"></i>
    </button>
<span class="float-right badge text-info"><?php passthru('sudo /opt/de_GWD/ui-checkEditionARM');?></span>

    <!-- Navbar Search -->
    <form class="d-none d-md-inline-block form-inline ml-auto mr-0 mr-md-3 my-2 my-md-0">
    </form>

    <!-- Navbar -->
    <ul class="nav navbar-nav ml-auto ml-md-0">
      <li class="nav-item no-arrow mx-1" style="display:<?php if ($checkJellyfin === 'installed') echo 'block'; else echo 'none';?>">
        <a class="nav-link" href="javascript:void(0)" onclick="window.open(location.origin+':8097')">
          <i class="fab fa-youtube"></i>
          <span>Jellyfin</span>
        </a>
      </li>

      <li class="nav-item no-arrow mx-1" style="display:<?php if ($checkBitwarden === 'installed') echo 'block'; else echo 'none';?>">
        <a class="nav-link" href="javascript:void(0)" onclick="window.open(location.origin+':8099')">
          <i class="fas fa-shield-alt"></i>
          <span>Bitwarden</span>
        </a>
      </li>
      
      <li class="nav-item no-arrow mx-1">
        <a class="nav-link" href="/admin/" onclick="javascript:event.target.port=location.port" target="_blank">
          <i class="fab fa-raspberry-pi"></i>
          <span>Pi-Hole</span>
        </a>
      </li>
    </ul>

  </nav>

  <div id="wrapper">

    <!-- Sidebar -->
    <ul class="sidebar navbar-nav toggled">
      <li class="nav-item active">
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
      <li class="nav-item">
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

        <!-- Breadcrumbs -->
        <ol class="breadcrumb">
          <li class="breadcrumb-item">
            <a href="index.php">概览</a>
          </li>
          <li class="breadcrumb-item active">状态</li>
        </ol>

        <!-- Icon Cards -->
        <div class="row">
          <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-dark o-hidden h-100">
              <div class="card-body">
                <div class="card-body-icon">
                  <i class="fas fa-rocket"></i>
                </div>
                <div class="form-row align-items-center">联网状态</div>
              </div>
              <a class="card-footer text-white clearfix small z-1">
                <span id="testBaidu" class="float-left"></span>
                <span id="testYoutue" class="float-right"></span>
              </a>
            </div>
          </div>

          <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-dark o-hidden h-100">
              <div class="card-body">
                <div class="card-body-icon">
                  <i class="fas fa-bell"></i>
                </div>
                <div class="form-row align-items-center">版本检测</div>
              </div>
              <a class="card-footer text-white clearfix small z-1">
                <h6 class="float-left" style="margin-bottom: 0"><span id="currentver" class="" style="font-size:0.75rem;font-weight:600;line-height:0.35;border-radius:10rem;"></span></h6>
                <h6 class="float-right" style="margin-bottom: 0"><span id="remotever" class="" style="font-size:0.75rem;font-weight:600;line-height:0.35;border-radius:10rem;"></span></h6>
              </a>
            </div>
          </div>
          
          <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-dark o-hidden h-100">
              <div class="card-body">
                <div class="card-body-icon">
                  <i class="fas fa-toggle-on"></i>
                </div>
                <div class="form-row align-items-center">
                  <span>代理开关</span>
                  <span id="buttonProxyLoading"></span>
                </div>
              </div>
              <a class="card-footer text-white clearfix small z-1">
                <h6 id="buttonProxyRestart" class="float-left" style="margin-bottom: 0"><button class="btn btn-light" style="font-size:0.75rem;font-weight:600;line-height:0.35;border-radius:10rem;">重启代理</button></h6>
                <h6 id="buttonProxyStop" class="float-right" style="margin-bottom: 0"><button class="btn btn-light" style="font-size:0.75rem;font-weight:600;line-height:0.35;border-radius:10rem;">关闭代理</button></h6>
              </a>
            </div>
          </div>

          <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-dark o-hidden h-100">
              <div class="card-body">
                <div class="card-body-icon">
                  <i class="fas fa-clock"></i>
                </div>
                  <div class="form-row align-items-center">运行时长</div>
              </div>
              <a class="card-footer text-white clearfix small z-1">
                <span id="uptime" class="float-left"></span>
                <span class="float-right"><?php $kernelV = php_uname('r'); echo str_replace("-arm64", "", str_replace("-amd64", "", $kernelV));?></span>
              </a>
            </div>
          </div>
        </div>


<!-- Modal -->
<div id="markThis" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="markThisLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="markThisLabel" class="modal-title">备注本机</h5>
      </div>
      <div class="modal-body">
        <input id="markName" type="text" class="form-control" placeholder="备注名" required="required" value="<?php echo htmlspecialchars($de_GWDconf->address->alias ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="modal-footer">
        <button id="buttonMarkThis" type="button" class="btn btn-outline-dark btn-sm">应用</button>
      </div>
    </div>
  </div>
</div>

<div id="nodeSMrules" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2 bg-light">
        <h6 class="modal-title font-weight-bold text-dark"><i class="fas fa-sitemap mr-1"></i> 预定义服务分流矩阵 (NodeSM)</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body py-3">
        <div class="row">
          <!-- Left Column: AI & Streaming -->
          <div class="col-md-6 col-12">
            <h6 class="text-primary font-weight-bold mb-2 small"><i class="fas fa-robot mr-1"></i> AI 智能平台</h6>
            <?php
            $left_services = [
              'openai'   => ['name' => 'OpenAI / ChatGPT', 'color' => '#10a37f'],
              'claude'   => ['name' => 'Claude (Anthropic)', 'color' => '#d97706'],
              'gemini'   => ['name' => 'Google Gemini', 'color' => '#1a73e8'],
              'grok'     => ['name' => 'xAI Grok', 'color' => '#212529'],
            ];
            foreach ($left_services as $svc => $cfg) {
              $sname = $cfg['name'];
              $scolor = $cfg['color'];
              echo "<div class='input-group input-group-sm mb-2'>\n";
              echo "  <div class='input-group-prepend' style='width: 42%;'>\n";
              echo "    <span class='input-group-text w-100 text-white font-weight-bold text-truncate' style='background-color: {$scolor}; border-color: {$scolor};'>{$sname}</span>\n";
              echo "  </div>\n";
              echo "  <div class='input-group-append' style='width: 58%;'>\n";
              echo "    <button id='nodeSMshow_{$svc}' class='btn btn-outline-secondary dropdown-toggle border-left-0 w-100 text-truncate text-left' type='button' data-toggle='dropdown' value='0'> 默认代理 </button>\n";
              echo "    <div id='nodeSMlist_{$svc}' class='dropdown-menu w-100'>\n";
              echo "      <a class='dropdown-item' href='javascript:void(0);' onclick=\"selectNodeSM('{$svc}', 0, ' 默认代理 ')\"> 默认代理 </a>\n";
              for ($i = 0; $i < count($v2nodes); $i++) {
                $nname = htmlspecialchars($v2nodes[$i]->name ?? '', ENT_QUOTES, 'UTF-8');
                $num = $i + 1;
                echo "      <a class='dropdown-item' href='javascript:void(0);' onclick=\"selectNodeSM('{$svc}', {$num}, '{$nname}')\">{$nname}</a>\n";
              }
              echo "    </div>\n";
              echo "  </div>\n";
              echo "</div>\n";
            }
            ?>

            <h6 class="text-danger font-weight-bold mt-3 mb-2 small"><i class="fas fa-play-circle mr-1"></i> 流媒体影音</h6>
            <?php
            $stream_services = [
              'youtube'  => ['name' => 'YouTube', 'color' => '#dc2626'],
              'netflix'  => ['name' => 'Netflix', 'color' => '#b91c1c'],
              'hdh'      => ['name' => 'Disney+ / HBO / Hulu', 'color' => '#6d28d9'],
              'bahamut'  => ['name' => '巴哈姆特動畫瘋', 'color' => '#0891b2'],
              'tvb'      => ['name' => 'TVB', 'color' => '#2563eb'],
            ];
            foreach ($stream_services as $svc => $cfg) {
              $sname = $cfg['name'];
              $scolor = $cfg['color'];
              echo "<div class='input-group input-group-sm mb-2'>\n";
              echo "  <div class='input-group-prepend' style='width: 42%;'>\n";
              echo "    <span class='input-group-text w-100 text-white font-weight-bold text-truncate' style='background-color: {$scolor}; border-color: {$scolor};'>{$sname}</span>\n";
              echo "  </div>\n";
              echo "  <div class='input-group-append' style='width: 58%;'>\n";
              echo "    <button id='nodeSMshow_{$svc}' class='btn btn-outline-secondary dropdown-toggle border-left-0 w-100 text-truncate text-left' type='button' data-toggle='dropdown' value='0'> 默认代理 </button>\n";
              echo "    <div id='nodeSMlist_{$svc}' class='dropdown-menu w-100'>\n";
              echo "      <a class='dropdown-item' href='javascript:void(0);' onclick=\"selectNodeSM('{$svc}', 0, ' 默认代理 ')\"> 默认代理 </a>\n";
              for ($i = 0; $i < count($v2nodes); $i++) {
                $nname = htmlspecialchars($v2nodes[$i]->name ?? '', ENT_QUOTES, 'UTF-8');
                $num = $i + 1;
                echo "      <a class='dropdown-item' href='javascript:void(0);' onclick=\"selectNodeSM('{$svc}', {$num}, '{$nname}')\">{$nname}</a>\n";
              }
              echo "    </div>\n";
              echo "  </div>\n";
              echo "</div>\n";
            }
            ?>
          </div>

          <!-- Right Column: Social, Dev, Games & Apple -->
          <div class="col-md-6 col-12">
            <h6 class="text-info font-weight-bold mb-2 small"><i class="fas fa-globe mr-1"></i> 社群与开发</h6>
            <?php
            $social_services = [
              'telegram'  => ['name' => 'Telegram', 'color' => '#0284c7'],
              'twitter'   => ['name' => 'X (Twitter)', 'color' => '#0f172a'],
              'discord'   => ['name' => 'Discord', 'color' => '#5865f2'],
              'reddit'    => ['name' => 'Reddit', 'color' => '#ea580c'],
              'github'    => ['name' => 'GitHub', 'color' => '#334155'],
              'wikipedia' => ['name' => '维基百科', 'color' => '#475569'],
            ];
            foreach ($social_services as $svc => $cfg) {
              $sname = $cfg['name'];
              $scolor = $cfg['color'];
              echo "<div class='input-group input-group-sm mb-2'>\n";
              echo "  <div class='input-group-prepend' style='width: 42%;'>\n";
              echo "    <span class='input-group-text w-100 text-white font-weight-bold text-truncate' style='background-color: {$scolor}; border-color: {$scolor};'>{$sname}</span>\n";
              echo "  </div>\n";
              echo "  <div class='input-group-append' style='width: 58%;'>\n";
              echo "    <button id='nodeSMshow_{$svc}' class='btn btn-outline-secondary dropdown-toggle border-left-0 w-100 text-truncate text-left' type='button' data-toggle='dropdown' value='0'> 默认代理 </button>\n";
              echo "    <div id='nodeSMlist_{$svc}' class='dropdown-menu w-100'>\n";
              echo "      <a class='dropdown-item' href='javascript:void(0);' onclick=\"selectNodeSM('{$svc}', 0, ' 默认代理 ')\"> 默认代理 </a>\n";
              for ($i = 0; $i < count($v2nodes); $i++) {
                $nname = htmlspecialchars($v2nodes[$i]->name ?? '', ENT_QUOTES, 'UTF-8');
                $num = $i + 1;
                echo "      <a class='dropdown-item' href='javascript:void(0);' onclick=\"selectNodeSM('{$svc}', {$num}, '{$nname}')\">{$nname}</a>\n";
              }
              echo "    </div>\n";
              echo "  </div>\n";
              echo "</div>\n";
            }
            ?>

            <h6 class="text-secondary font-weight-bold mt-3 mb-2 small"><i class="fas fa-shield-alt mr-1"></i> 系统直连与游戏</h6>
            <!-- Apple Special -->
            <div class="input-group input-group-sm mb-2">
              <div class="input-group-prepend" style="width: 42%;">
                <span class="input-group-text w-100 text-white font-weight-bold text-truncate" style="background-color: #52525b; border-color: #52525b;">Apple 服务</span>
              </div>
              <div class="input-group-append" style="width: 58%;">
                <button id="nodeSMshow_apple" class="btn btn-outline-secondary dropdown-toggle border-left-0 w-100 text-truncate text-left" type="button" data-toggle="dropdown" value="0"> 直连 </button>
                <div id="nodeSMlist_apple" class="dropdown-menu w-100">
                  <a class="dropdown-item" href="javascript:void(0);" onclick="selectNodeSM('apple', 0, ' 直连 ')"> 直连 </a>
                  <?php
                  for ($i = 0; $i < count($v2nodes); $i++) {
                    $nname = htmlspecialchars($v2nodes[$i]->name ?? '', ENT_QUOTES, 'UTF-8');
                    $num = $i + 1;
                    echo "<a class='dropdown-item' href='javascript:void(0);' onclick=\"selectNodeSM('apple', {$num}, '{$nname}')\">{$nname}</a>\n";
                  }
                  ?>
                </div>
              </div>
            </div>

            <!-- Steam Special -->
            <div class="input-group input-group-sm mb-2">
              <div class="input-group-prepend" style="width: 42%;">
                <span class="input-group-text w-100 text-white font-weight-bold text-truncate" style="background-color: #1e293b; border-color: #1e293b;">Steam/国区游戏</span>
              </div>
              <div class="input-group-append" style="width: 58%;">
                <button id="nodeSMshow_steam" class="btn btn-outline-secondary dropdown-toggle border-left-0 w-100 text-truncate text-left" type="button" data-toggle="dropdown" value="0"> 直连 </button>
                <div id="nodeSMlist_steam" class="dropdown-menu w-100">
                  <a class="dropdown-item" href="javascript:void(0);" onclick="selectNodeSM('steam', 0, ' 直连 ')"> 直连 </a>
                  <a class="dropdown-item" href="javascript:void(0);" onclick="selectNodeSM('steam', 1, ' 默认代理 ')"> 默认代理 </a>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button id="submitNodeSMrulesClear" type="button" class="btn btn-outline-secondary btn-sm">
          <span>重置默认</span>
        </button>
        <button id="submitNodeSMrules" type="button" class="btn btn-primary btn-sm">
          <span id="submitNodeSMrulesLoading"></span>
          <span>保存并应用规则</span>
        </button>
      </div>
    </div>
  </div>
</div>

<div id="nodeDTrules" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">

<div class="input-group input-group-sm mb-3">
  <div class="input-group-prepend">
    <span class="input-group-text">大陆白名单代理</span>
  </div>
   <input id="CHNlistProxyIP" type="text" class="form-control" placeholder="本地局域网IP 空格分隔" value="<?php if (!empty($de_GWDconf->v2nodeDIV->nodeDT->CHNlistProxyIP)) { foreach ($de_GWDconf->v2nodeDIV->nodeDT->CHNlistProxyIP as $k => $v) {echo htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . ' ';} } ?>">
</div>

<div class="input-group input-group-sm mb-3">
  <div class="input-group-prepend">
    <span class="input-group-text">全局代理</span>
  </div>
   <input id="globalProxyIP" type="text" class="form-control" placeholder="本地局域网IP 空格分隔" value="<?php if (!empty($de_GWDconf->v2nodeDIV->nodeDT->globalProxyIP)) { foreach ($de_GWDconf->v2nodeDIV->nodeDT->globalProxyIP as $k => $v) {echo htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . ' ';} } ?>">
</div>

<div class="input-group input-group-sm">
  <div class="input-group-prepend">
    <span class="input-group-text">全局直连</span>
  </div>
   <input id="directProxyIP" type="text" class="form-control" placeholder="本地局域网IP 空格分隔" value="<?php if (!empty($de_GWDconf->v2nodeDIV->nodeDT->directProxyIP)) { foreach ($de_GWDconf->v2nodeDIV->nodeDT->directProxyIP as $k => $v) {echo htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . ' ';} } ?>">
</div>

      </div>
      <div class="modal-footer">
        <button id="buttonNodeDTrules" type="button" class="btn btn-outline-secondary btn-sm">
          <span id="buttonNodeDTrulesloading"></span>
          <span>规则写入</span>
        </button>
      </div>
    </div>
  </div>
</div>

<div id="nodeCUrules" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <textarea id="nodeCUrulesDomain" class="form-control mb-2" rows="12"><?php $customDomainList = $de_GWDconf->v2nodeDIV->nodeCU->rulesDomain ?? []; if (is_array($customDomainList)) { foreach ( $customDomainList as $k => $v) {if (end($customDomainList) == $v) echo "$v"; else echo "$v\n";} } ?></textarea>
        <div class="alert-light mb-4" role="alert">
        纯字符串: google.com<br>
        子域名: domain:google.com<br>
        子串: keyword:google.com<br>
        正则: regexp:\\.goo.*\\.com$<br>
        预定义域名列表: geosite:google<br>
        </div>

        <textarea id="nodeCUrulesIP" class="form-control mb-2" rows="6"><?php $customDomainList = $de_GWDconf->v2nodeDIV->nodeCU->rulesIP ?? []; if (is_array($customDomainList)) { foreach ( $customDomainList as $k => $v) {if (end($customDomainList) == $v) echo "$v"; else echo "$v\n";} } ?></textarea>
        <div class="alert-light" role="alert">
        使用非内网IP，非专用网络IP<br>
        IP: 104.24.0.0<br>
        CIDR: 104.24.0.0/14<br>
        预定义IP列表:<br>
        geoip:cloudflare<br>
        geoip:cloudfront<br>
        geoip:facebook<br>
        geoip:fastly<br>
        geoip:google<br>
        geoip:netflix<br>
        geoip:telegram<br>
        geoip:twitter<br>
        </div>
      </div>
      <div class="modal-footer">
        <button id="buttonNodeCUrules" type="button" class="btn btn-outline-secondary btn-sm">
          <span id="buttonNodeCUrulesloading"></span>
          <span>规则写入</span>
        </button>
      </div>
    </div>
  </div>
</div>

<div id="ssDetail" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-body">
        <div class="input-group mb-3">
          <div class="input-group-prepend">
            <span class="input-group-text">链接</span>
          </div>
          <input id="ssLink" class="form-control" placeholder="粘贴 vless://... Reality 节点链接自动解析填充">
        </div>

        <div class="input-group mb-2">
          <div class="input-group-prepend">
            <span class="input-group-text">地址</span>
          </div>
          <input id="ssAddress" class="form-control" value="<?php echo htmlspecialchars($de_GWDconf->v2nodeDIV->ss->ssAddress ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <div class="input-group-prepend input-group-append">
            <span class="input-group-text">端口</span>
          </div>
          <input id="ssPort" class="form-control" value="<?php echo htmlspecialchars($de_GWDconf->v2nodeDIV->ss->ssPort ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="443">
        </div>

        <div class="input-group mb-2">
          <div class="input-group-prepend">
            <span class="input-group-text">UUID</span>
          </div>
          <input id="ssUUID" class="form-control" value="<?php echo htmlspecialchars($de_GWDconf->v2nodeDIV->ss->ssUUID ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="input-group mb-2">
          <div class="input-group-prepend">
            <span class="input-group-text">SNI</span>
          </div>
          <input id="ssSNI" class="form-control" value="<?php echo htmlspecialchars($de_GWDconf->v2nodeDIV->ss->ssSNI ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text">PublicKey</span>
          </div>
          <input id="ssPBK" class="form-control" value="<?php echo htmlspecialchars($de_GWDconf->v2nodeDIV->ss->ssPBK ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <div class="input-group-prepend input-group-append">
            <span class="input-group-text">ShortId</span>
          </div>
          <input id="ssSID" class="form-control" value="<?php echo htmlspecialchars($de_GWDconf->v2nodeDIV->ss->ssSID ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button id="buttonSSclear" type="button" class="btn btn-outline-secondary btn-sm">
          <span id="buttonSSclearloading"></span>
          <span>清除</span>
        </button>

        <button id="buttonSSsubmit" type="button" class="btn btn-outline-secondary btn-sm">
          <span id="buttonSSsubmitloading"></span>
          <span>应用</span>
        </button>
      </div>
    </div>
  </div>
</div>

<div id="editListBW" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
              <div class="card">
                <div class="card-header">
                  <span>黑名单域名：</span>
                </div>
                  <textarea id="listB" class="form-control" aria-label="listB" rows="12" placeholder="一行一个域名" style="border-Radius: 0px;"><?php if (!empty($de_GWDconf->dns->listB)) { foreach($de_GWDconf->dns->listB as $v) {echo "$v\n";} } ?></textarea>
              </div>

              <div class="card mt-3">
                <div class="card-header">
                  <span>白名单域名：</span>
                </div>
                  <textarea id="listW" class="form-control" aria-label="listW" rows="12" placeholder="一行一个域名"><?php if (!empty($de_GWDconf->dns->listW)) { foreach($de_GWDconf->dns->listW as $v) {echo "$v\n";} } ?></textarea>
              </div>
      </div>
      <div class="modal-footer">
        <button id="buttonListBWurl" type="button" class="btn btn-outline-secondary btn-sm">
          <span id="buttonListBWurlloading"></span>
          <span>黑白名单写入</span>
        </button>
      </div>
    </div>
  </div>
</div>

<div id="reboot" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="reboot" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <span class="modal-title">确认重启并清理内核</span>
      </div>

      <div class="modal-footer">
        <button id="buttonReboot" type="button" class="btn btn-sm btn-danger" style="border-radius: 0px;">立即重启</button>
      </div>
    </div>
  </div>
</div>

        <!-- DataTables Example -->
        <div class="card mb-3">
          <div class="card-header">
            <i class="fas fa-stream"></i>
            节点列表
         <span class="float-right mt-n1 mb-n2">
                <button type="button" class="btn btn-sm btn-outline-secondary mt-1 mr-2" style="border-radius: 0px;" data-toggle="modal" data-target="#nodeSMrules">
                  <span>预定义分流</span>
                </button>

                <button id="buttonSSdetail" type="button" class="btn <?php passthru('/opt/de_GWD/ui-checkNodeSS'); ?> btn-sm mt-1 mr-2" style="border-radius: 0px;" data-toggle="modal" data-target="#ssDetail">
                  <span>Reality</span>
                </button>

                <button id="buttonclearDIV" type="button" class="btn btn-outline-secondary btn-sm mt-1" style="border-radius: 0px;">
                  <span id="buttonclearDIVloading"></span>
                  <span>清理所有分流</span>
                </button>
          </span>
          </div>
          <div style="display: flex;flex-wrap: wrap;">
            <button id="buttonPingTCP" type="button" class="btn btn-outline-success btn-sm col-12" style="border-radius: 0px;">Ping (TCP)</button>
          </div>

          <div class="card-body">
<div class="form-row">
<div class="mx-4 mr-auto">
<div class="input-group input-group-sm mb-3">
  <div class="input-group-prepend">
    <span class="input-group-text">本地局域网分流</span>
  </div>
  <div id="nodeDTlist" class="input-group-prepend" style="display:none">
    <button id="nodeDTshow" class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"></button>
    <div id="nodeDT" class="dropdown-menu">
<?php
for( $i=0; $i<count($v2nodes); $i++){
  $name = htmlspecialchars($v2nodes[$i]->name ?? '', ENT_QUOTES, 'UTF-8');
print <<<EOT
<a class='dropdown-item' href='#' id='nodeDT$i' onclick="buttonNodeDT(this)">$name</a>
EOT;
}
?>
    </div>
  </div>
  <div id="nodeDTipButton" class="input-group-prepend input-group-append" style="display:none">
    <button class="btn btn-outline-secondary border-left-0" type="button" data-toggle="modal" data-target="#nodeDTrules">规则</button>
  </div>
  <div class="input-group-append">
    <button class="btn btn-secondary" type="button" onclick="nodeDTswitch(this)">
      <span id="buttonNodeDTloading"></span>
      <span id="nodeDTtext">ON</span>
    </button>
  </div>
</div>
</div>

<div class="mx-4">
<div class="input-group input-group-sm mb-3">
  <div class="input-group-prepend">
    <span class="input-group-text">自定义分流</span>
  </div>
  <div id="nodeCUlist" class="input-group-prepend" style="display:none">
    <button id="nodeCUshow" class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"></button>
    <div id="nodeCU" class="dropdown-menu">
<?php
for( $i=0; $i<count($v2nodes); $i++){
  $name = htmlspecialchars($v2nodes[$i]->name ?? '', ENT_QUOTES, 'UTF-8');
print <<<EOT
<a class='dropdown-item' href='#' id='nodeCU$i' onclick="buttonNodeCU(this)">$name</a>
EOT;
}
?>
    </div>
  </div>
  <div id="nodeCUrulesButton" class="input-group-prepend" style="display:none">
    <button class="btn btn-outline-secondary border-left-0" type="button" data-toggle="modal" data-target="#nodeCUrules">规则</button>
  </div>
  <div class="input-group-append">
    <button class="btn btn-secondary" type="button" onclick="nodeCUswitch(this)">
      <span id="buttonNodeCUloading"></span>
      <span id="nodeCUtext">ON</span>
    </button>
  </div>
</div>
</div>

</div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover text-center text-nowrap my-2">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>节点地址</th>
                    <th class="text-left">节点名</th>
                    <th>延迟(ms)</th>
                    <th>速度(MB/s)</th>
                    <th>操作</th>
                  </tr>
                </thead>
                <tbody id="nodeTable">
<?php
  $checkNode = exec('/opt/de_GWD/ui-checkNode');
for( $i=0; $i<count($v2nodes); $i++){
  $nodeNUM = $i+1;
  $domain = htmlspecialchars($v2nodes[$i]->domain ?? '', ENT_QUOTES, 'UTF-8');
  $name = htmlspecialchars($v2nodes[$i]->name ?? '', ENT_QUOTES, 'UTF-8');
  if ( $checkNode != null && $checkNode == $i) $checkStatus = "btn-success"; else $checkStatus = "btn-outline-secondary";
print <<<EOT
                  <tr> 
                    <td class="align-middle">$nodeNUM</td>
                    <td class="align-middle"><span id="nodeDomain{$i}">{$domain}</span></td>
                    <td class="text-left"><span id="nodeshow{$i}">{$name}</span></td>
                    <td class="align-middle"><span id="ping{$i}" class='text-success'></span></td>
                    <td class="align-middle"><span id="speed{$i}" class='text-success'><a href="javascript:void(0)" class="text-success" onclick="buttonSpeed(this)"><i class="far fa-play-circle fa-lg"></i></a></span></td>
                    <td class="align-middle"><button id="switch{$i}" type="button" class="btn $checkStatus btn-sm" onclick="buttonSwitch(this)">切换</button></td>
                  </tr>
EOT;
}
?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- row2 -->
        <div class="card mb-3">
          <div class="card-header">
            <i class="far fa-compass"></i>
            DNS
          <span class="float-right mt-n1 mb-n2">
                <button type="button" class="btn btn-secondary btn-sm mt-1 mr-2" style="border-radius: 0px;" data-toggle="modal" data-target="#editListBW">
                  <span>黑白名单</span>
                </button>

                <div class="btn-group">
                  <button id="buttonDNSsubmit" type="button" class="btn btn-secondary btn-sm mt-1" style="border-radius: 0px;">
                    <span id="buttonDNSsubmitloading"></span>
                    <span>应用</span>
                  </button>
                </div>
          </span>
          </div>

          <div class="card-body">
            <div class="form-row">
              <div class="col-md-5">
              <div class="input-group my-2">
                <div class="input-group-prepend">
                  <span class="input-group-text" style="min-width: 75px">DoH 1</span>
                </div>
                <input type="text" id="DoH1" class="form-control" placeholder="域名:端口/dq" required="required" value="<?php echo htmlspecialchars($de_GWDconf->dns->doh[0] ?? '', ENT_QUOTES, 'UTF-8');?>">
                <div class="input-group-append">
                  <span id="pingDOH1" class="input-group-text text-success"></span><span class="input-group-text text-secondary">ms</span>
                </div>
              </div>

              <div class="input-group my-2">
                <div class="input-group-prepend">
                  <span class="input-group-text" style="min-width: 75px">DoH 2</span>
                </div>
                <input type="text" id="DoH2" class="form-control" placeholder="域名:端口/dq" required="required" value="<?php echo htmlspecialchars($de_GWDconf->dns->doh[1] ?? '', ENT_QUOTES, 'UTF-8');?>">
                <div class="input-group-append">
                  <span id="pingDOH2" class="input-group-text text-success"></span><span class="input-group-text text-secondary">ms</span>
                </div>
              </div>

              <div class="input-group my-2">
                <div class="input-group-prepend">
                  <span class="input-group-text" style="min-width: 75px">DNS over gRPC</span>
                </div>
                <input type="text" id="DoGc" class="form-control" placeholder="域名:端口" required="required" value="<?php echo htmlspecialchars($DoG, ENT_QUOTES, 'UTF-8'); ?>" style="display:<?php if (empty($DoG) === false) echo 'block'; else echo 'none';?>">
                <div id="DoGping" class="input-group-prepend input-group-append" style="display:<?php if (empty($DoG) === false) echo 'block'; else echo 'none';?>">
                  <span id="pingDoG" class="form-control input-group-text text-success"></span>
                </div>
                <div id="DoGpingMS" class="input-group-append" style="display:<?php if (empty($DoG) === false) echo 'block'; else echo 'none';?>">
                  <span class="input-group-text text-secondary">ms</span>
                </div>
                <div id="DoGbutton" class="input-group-prepend" style="display:<?php if (empty($DoG) === true) echo 'block'; else echo 'none';?>">
                  <button id="openDoG" class="form-control btn btn-outline-secondary" type="button"><i class="fas fa-angle-right"></i></button>
                </div>
              </div>
              </div>

                <div class="input-group col-md-3 my-2">
                <div class="input-group-prepend">
                  <span class="input-group-text">
                  DNS<br>
                  国内<br>
                  </span>
                </div>
                  <textarea id="dnsChina" class="form-control" aria-label="dnsChina" rows="6"><?php
$smartdnsConf = file_exists('/opt/de_GWD/smartdns/smartdns.conf') ? file_get_contents('/opt/de_GWD/smartdns/smartdns.conf') : '';
preg_match_all('/(?<=\bserver )\S+/is', $smartdnsConf, $dnsClist);
if(($index = array_search('1.1.1.1',$dnsClist[0])) !== false){
unset($dnsClist[0][$index]);
}
if(($index = array_search('8.8.8.8',$dnsClist[0])) !== false){
unset($dnsClist[0][$index]);
}
if(($index = array_search('127.0.0.1:5333',$dnsClist[0])) !== false){
unset($dnsClist[0][$index]);
}
foreach(array_unique($dnsClist[0]) as $k => $v){
  if ($v == end($dnsClist[0]))
    echo $v;
  else
    echo "$v\n";
}
?></textarea>
                </div>

                <div class="input-group col-md-4 my-2">
                <div class="input-group-prepend">
                  <span class="input-group-text">
                  hosts<br>
                  静态<br>
                  </span>
                </div>
                  <textarea id="hostsCustomize" class="form-control" aria-label="hostsCustomize" rows="6" placeholder="IP 空格 abc.com &#10;IP 空格 *.abc.com"><?php passthru("sudo /opt/de_GWD/ui-hostsCustomize"); ?></textarea>
                </div>
            </div>

          </div>
        </div>

        <!-- row3 -->
        <div class="form-row">
        <!-- 静态地址 -->
          <div class="col-md-6">
        <div class="card mb-3">
          <div class="card-header">
            <i class="fas fa-exchange-alt"></i>
            IP地址
          <span class="float-right mt-n1 mb-n2">
                <button type="button" class="btn btn-secondary btn-sm mt-1" style="border-radius: 0px;" data-toggle="modal" data-target="#reboot">应用 & 重启</button>
          </span>
          </div>
          <div class="card-body">
                <div class="form-row">
                <div class="col-md-6">
                <div class="input-group my-2">
                  <input id="localip" type="text" class="form-control" placeholder="本机地址" required="required" value="<?php echo htmlspecialchars($de_GWDconf->address->localIP ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="input-group-append">
                  <span class="input-group-text text-secondary">本机</span>
                </div>
                </div>
                </div>
                <div class="col-md-6">
                <div class="input-group my-2">
                  <input id="upstreamip" type="text" class="form-control" placeholder="上级地址" required="required" value="<?php echo htmlspecialchars($de_GWDconf->address->upstreamIP ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="input-group-append">
                  <span class="input-group-text text-secondary">上级</span>
                </div>
                </div>
                </div>
                </div>
          </div>
          </div>
          </div>


        </div>



        </div>
        <!-- /.container-fluid -->
      </div>
      <!-- /.content-wrapper -->

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
var checklinkBaiduPending = false;
var checklinkGlobalPending = false;

function checklink(){
if (!checklinkBaiduPending) {
  checklinkBaiduPending = true;
  $.get('./act/testBaidu.php',function(data){
    if ( $.trim(data) == "ONLINE" ) {
      $('#testBaidu').text("✓ 国内线路畅通")
    } else {
      $('#testBaidu').text("✗ 国内线路不通")
    }
  }).always(function(){
    checklinkBaiduPending = false;
  })
}

if (!checklinkGlobalPending) {
  checklinkGlobalPending = true;
  $.get('./act/testGlobal.php',function(data){
    if ( $.trim(data) == "ONLINE" ) {
      $('#testYoutue').text("✓ 国外线路畅通")
    } else {
      $('#testYoutue').text("✗ 国外线路不通")
    }
  }).always(function(){
    checklinkGlobalPending = false;
  })
}
}

function checkVersion(){
$.get("./act/version.php", function(data){
var parts = (data || '').split("-");
var currentvernum = $.trim(parts[0]) || '未知';
var remotevernum = parts.length > 1 && $.trim(parts[1]) !== '' ? $.trim(parts[1]) : currentvernum;
var vera = currentvernum;
var verb = remotevernum;
$('#currentver').attr('class','btn btn-light').text(currentvernum+' 本机')
$('#remotever').attr('class','btn btn-light').text(remotevernum+' 发布')
if (vera != verb) {
$('#remotever').attr('class','btn btn-warning')
}
})
}

function nodeDTswitch(nodeDTswitch){
$("#buttonNodeDTloading").attr("class", "spinner-border spinner-border-sm")
var nodeDTtext = $(nodeDTswitch).find('#nodeDTtext').html()
if (nodeDTtext == 'OFF'){
$.get('./act/NodeDTswitch.php', {switchNodeDT:"NodeDThide"}, function(result){
  $("#nodeDTlist").css("display", "none")
  $("#nodeDTiptext").css("display", "none")
  $("#nodeDTipButton").css("display", "none")
  $("#buttonNodeDTloading").removeClass()
  $("#nodeDTtext").html("ON")
})
}
else {
$.get('./act/NodeDTswitch.php', {switchNodeDT:"NodeDTshow"}, function(data){
  $("#nodeDTlist").css("display", "block")
  $("#nodeDTiptext").css("display", "block")
  $("#nodeDTipButton").css("display", "block")
  $("#buttonNodeDTloading").removeClass()
  $("#nodeDTshow").html(data)
  $("#nodeDTtext").html("OFF")
})
}
}

function nodeCUswitch(nodeCUswitch){
$("#buttonNodeCUloading").attr("class", "spinner-border spinner-border-sm")
var nodeCUtext = $(nodeCUswitch).find('#nodeCUtext').html()
if (nodeCUtext == 'OFF'){
$.get('./act/NodeCUswitch.php', {switchNodeCU:"NodeCUhide"}, function(result){
  $("#nodeCUlist").css("display", "none")
  $("#nodeCUrulesButton").css("display", "none")
  $("#nodeCUtext").html("ON")
  $("#buttonNodeCUloading").removeClass()
})
}
else {
$.get('./act/NodeCUswitch.php', {switchNodeCU:"NodeCUshow"}, function(data){
  $("#nodeCUlist").css("display", "block")
  $("#nodeCUrulesButton").css("display", "block")
  $('#nodeCUshow').html(data)
  $("#nodeCUtext").html("OFF")
  $("#buttonNodeCUloading").removeClass()
})
}
}

function buttonSpeed(buttonSpeed){
var SPEEDobj = $(buttonSpeed).parent().parent().parent().parent().find('tr')
var SPEEDindex = SPEEDobj.index($(buttonSpeed).parent().parent().parent()[0])
$('#speed'+SPEEDindex).empty()
$('#speed'+SPEEDindex).attr('class', 'cloud')
$.get("./act/speedT.php", {speedT:SPEEDindex}, function(data){$('#speed'+SPEEDindex).attr('class', 'text-success'); $('#speed'+SPEEDindex).text(data)})
}

function buttonSwitch(buttonSwitch){
var SWITCHobj = $(buttonSwitch).parent().parent().parent().find('tr')
var SWITCHindex = SWITCHobj.index($(buttonSwitch).parent().parent()[0])
$("#nodeTable td:nth-child(6) button").attr('class', "btn btn-outline-secondary btn-sm")
$("#buttonSSdetail").attr('class','btn btn-outline-secondary btn-sm mt-1')
$('#switch'+SWITCHindex).attr('class', 'btn btn-success btn-sm');
$.get("./act/NodeChange.php", {nodenum:SWITCHindex}, function(){})
}

function selectNodeSM(svc, idx, name){
  $('#nodeSMshow_' + svc).html(name);
  $('#nodeSMshow_' + svc).val(idx);
}

function buttonNodeDT(buttonNodeDT){
var NodeDTobj = $(buttonNodeDT).parent().find('a')
var NodeDTindex = NodeDTobj.index($(buttonNodeDT))
var NodeDTname = $('#nodeDT'+NodeDTindex).html()
$('#nodeDTshow').html(NodeDTname)
$.get("./act/NodeDTchange.php", {nodeDTnum:NodeDTindex}, function(){})
}

function buttonNodeCU(buttonNodeCU){
var NodeCUobj = $(buttonNodeCU).parent().find('a')
var NodeCUindex = NodeCUobj.index($(buttonNodeCU))
var NodeCUname = $('#nodeCU'+NodeCUindex).html()
$('#nodeCUshow').html(NodeCUname)
$.get("./act/NodeCUchange.php", {nodeCUnum:NodeCUindex}, function(){})
}

$(function(){
$('#buttonLogout').click(function(){
$.get('auth.php', {logout:'true'}, function(result){window.location.href="index.php"})
})

$('#buttonMarkThis').click(function(){
markNametxt=$('#markName').val()
$.get('./act/markThis.php', {markName:markNametxt}, function(result){window.location.reload()})
})

$('#currentver').click(function(){
window.open("https://github.com/stuinxo/de_GWD/releases")
})

$('#remotever').click(function(){
window.open("https://t.me/de_GWD_info")
})

$('#buttonProxyRestart').click(function(){
$("#buttonProxyLoading").attr("class", "spinner-border spinner-border-sm ml-2")
$.get('./act/proxyRestart.php', function(result){
  $("#buttonProxyLoading").removeClass()
})
})

$('#buttonProxyStop').click(function(){
$("#buttonProxyLoading").attr("class", "spinner-border spinner-border-sm ml-2")
$.get('./act/proxyStop.php', function(result){
  $("#buttonProxyLoading").removeClass()
})
})

$('#buttonPingTCP').click(function(){
$('#nodeTable tr').each(function(i){
$.get("./act/pingTCP.php", {pingTCP:i}, function(data){ $('#ping'+i).text(data) })
})
$.get("./act/pingTCPDOH1.php", function(data) { $('#pingDOH1').text(data) })
$.get("./act/pingTCPDOH2.php", function(data) { $('#pingDOH2').text(data) })
$.get("./act/pingTCPDoG.php", function(data) { $('#pingDoG').text(data) })
})

$('#submitNodeSMrulesClear').click(function(){
  var services = ['openai','claude','gemini','grok','youtube','netflix','hdh','bahamut','tvb','telegram','twitter','discord','reddit','github','wikipedia'];
  for (var i = 0; i < services.length; i++) {
    $('#nodeSMshow_' + services[i]).html(' 默认代理 ').val('0');
  }
  $('#nodeSMshow_apple').html(' 直连 ').val('0');
  $('#nodeSMshow_steam').html(' 直连 ').val('0');
});

$('#submitNodeSMrules').click(function(){
  $("#submitNodeSMrulesLoading").attr("class", "spinner-border spinner-border-sm ml-2");
  var params = {};
  var allServices = ['openai','claude','gemini','grok','youtube','netflix','hdh','bahamut','tvb','telegram','twitter','discord','reddit','github','wikipedia','apple','steam'];
  for (var i = 0; i < allServices.length; i++) {
    var s = allServices[i];
    params[s] = $('#nodeSMshow_' + s).val() || '0';
  }
  $.get('./act/NodeSMrules.php', params, function(result){
    $("#submitNodeSMrulesLoading").removeClass();
    $("#nodeSMrules").modal('hide');
    window.location.reload();
  });
});

$('#buttonclearDIV').click(function(){
$("#buttonclearDIVloading").attr("class", "spinner-border spinner-border-sm")
$.get('./act/NodeOne.php', function(result){
  $("#buttonclearDIVloading").removeClass()
  window.location.reload()
})
})

$('#buttonNodeDTrules').click(function(){
$("#buttonNodeDTrulesloading").attr("class", "spinner-border spinner-border-sm")
CHNlistProxyIP=$('#CHNlistProxyIP').val()
globalProxyIP=$('#globalProxyIP').val()
directProxyIP=$('#directProxyIP').val()
$.get('./act/NodeDTrules.php', {CHNlistProxyIP:CHNlistProxyIP, globalProxyIP:globalProxyIP, directProxyIP:directProxyIP}, function(result){
  $("#buttonNodeDTrulesloading").removeClass()
  $("#nodeDTrules").modal('hide')
})
})

$('#buttonNodeCUrules').click(function(){
$("#buttonNodeCUrulesloading").attr("class", "spinner-border spinner-border-sm")
customDomain=$('#nodeCUrulesDomain').val()
customIP=$('#nodeCUrulesIP').val()
$.get('./act/NodeCUrules.php', {customDomain:customDomain, customIP:customIP}, function(result){
  $("#buttonNodeCUrulesloading").removeClass()
  $("#nodeCUrules").modal('hide')
})
})

function parseVlessLink(raw) {
  raw = (raw || '').trim();
  if (!raw.startsWith('vless://')) return null;
  try {
    var url = new URL(raw);
    var uuid = url.username;
    var address = url.hostname;
    var port = url.port || '443';
    var sni = url.searchParams.get('sni') || url.searchParams.get('peer') || address;
    var pbk = url.searchParams.get('pbk') || url.searchParams.get('publicKey') || '';
    var sid = url.searchParams.get('sid') || url.searchParams.get('shortId') || '';
    return { uuid: uuid, address: address, port: port, sni: sni, pbk: pbk, sid: sid };
  } catch (e) {
    var m = raw.match(/^vless:\/\/([^@]+)@([^:?#]+)(?::([0-9]+))?(?:\?(.*))?$/);
    if (m) {
      var uuid = m[1];
      var address = m[2];
      var port = m[3] || '443';
      var search = m[4] ? m[4].split('#')[0] : '';
      var params = new URLSearchParams(search);
      var sni = params.get('sni') || params.get('peer') || address;
      var pbk = params.get('pbk') || params.get('publicKey') || '';
      var sid = params.get('sid') || params.get('shortId') || '';
      return { uuid: uuid, address: address, port: port, sni: sni, pbk: pbk, sid: sid };
    }
    return null;
  }
}

$('#ssLink').on('input change paste', function(){
  setTimeout(function(){
    var val = $('#ssLink').val();
    var p = parseVlessLink(val);
    if (p) {
      if (p.address) $('#ssAddress').val(p.address);
      if (p.port) $('#ssPort').val(p.port);
      if (p.uuid) $('#ssUUID').val(p.uuid);
      if (p.sni) $('#ssSNI').val(p.sni);
      if (p.pbk) $('#ssPBK').val(p.pbk);
      if (p.sid !== undefined) $('#ssSID').val(p.sid);
    }
  }, 50);
});

$('#buttonSSclear').click(function(){
$("#buttonSSclearloading").attr("class", "spinner-border spinner-border-sm")
$.get('./act/changeNodeSS0.php', function(result){
  $("#buttonSSclearloading").removeClass()
  window.location.reload()
})
})

$('#buttonSSsubmit').click(function(){
$("#buttonSSsubmitloading").attr("class", "spinner-border spinner-border-sm")
var ssAddress = ($('#ssAddress').val() || '').trim();
var ssPort = ($('#ssPort').val() || '').trim();
var ssUUID = ($('#ssUUID').val() || '').trim();
var ssSNI = ($('#ssSNI').val() || '').trim();
var ssPBK = ($('#ssPBK').val() || '').trim();
var ssSID = ($('#ssSID').val() || '').trim();
$.get('./act/changeNodeSS.php', {
  ssAddress: ssAddress,
  ssPort: ssPort,
  ssUUID: ssUUID,
  ssSNI: ssSNI,
  ssPBK: ssPBK,
  ssSID: ssSID
}, function(result){
  $("#buttonSSsubmitloading").removeClass()
  $("#ssDetail").modal('hide')
  $("#buttonSSdetail").attr('class','btn btn-success btn-sm mt-1')
  $("#nodeTable td:nth-child(6) button").attr('class', "btn btn-outline-secondary btn-sm")
})
})

$('#openDoG').click(function(){
$("#DoGc").css("display", "block")
$("#DoGping").css("display", "block")
$("#DoGpingMS").css("display", "block")
$("#DoGbutton").css("display", "none")
})

$('#buttonListBWurl').click(function(){
$("#buttonListBWurlloading").attr("class", "spinner-border spinner-border-sm")
listB=$("#listB").val()
listW=$("#listW").val()
$.get("./act/submitListBW.php", {listB:listB, listW:listW}, function(result){
  $("#buttonListBWurlloading").removeClass()
  $("#editListBW").modal('hide')
})
})

$('#buttonDNSsubmit').click(function(){
$("#buttonDNSsubmitloading").attr("class", "spinner-border spinner-border-sm")
DoGc=$('#DoGc').val()
doh1txt=$('#DoH1').val()
doh2txt=$('#DoH2').val()
dnsChina=$("#dnsChina").val()
hostsCustomize=$("#hostsCustomize").val()
$.get("./act/DNSsave.php", {DoGc:DoGc, DoH1:doh1txt, DoH2:doh2txt, dnsChina:dnsChina, hostsCustomize:hostsCustomize}, function(result){
  $("#buttonDNSsubmitloading").removeClass()
  window.location.reload()
})
})

$('#buttonReboot').click(function(){
staticip1=$('#localip').val()
staticip2=$('#upstreamip').val()
$.get('./act/reboot.php', {localip:staticip1, upstreamip:staticip2}, function(result){window.location.reload()})
})

checkVersion()
checklink()
setInterval(function() {
checklink()
}, 5000)

$.get('./act/uptime.php', function(data){$('#uptime').text(data)})

$.get('./act/NodeDTcheck.php', function(data){
if(data != "") {
  $('#nodeDTshow').html(data)
  $("#nodeDTlist").css("display", "block")
  $("#nodeDTiptext").css("display", "block")
  $("#nodeDTipButton").css("display", "block")
  $("#nodeDTtext").html("OFF")
}
})

$.get('./act/NodeCUcheck.php', function(data){
if(data != "") {
  $('#nodeCUshow').html(data)
  $("#nodeCUlist").css("display", "block")
  $("#nodeCUrulesButton").css("display", "block")
  $("#nodeCUtext").html("OFF")
}
})

$.get('./act/NodeSMcheck.php', function(data){
  try {
    var smData = (typeof data === 'object') ? data : JSON.parse(data);
    for (var svc in smData) {
      var item = smData[svc];
      if ($('#nodeSMshow_' + svc).length) {
        var num = item.num || 0;
        var name = item.name;
        if (!name || name === "-none-") {
          name = (svc === 'apple' || svc === 'steam') ? " 直连 " : " 默认代理 ";
        }
        $('#nodeSMshow_' + svc).val(num);
        $('#nodeSMshow_' + svc).html(name);
      }
    }
  } catch(e) {
    console.error("NodeSMcheck parse error", e);
  }
})
})
</script>

  <!-- Custom scripts for all pages-->
  <script src="js/sb-admin.min.js"></script>
  
</body>

</html>

<?php }?>
<?php  if(!$auth){ ?>
<?php header('Location: login.php');} ?>
