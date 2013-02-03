<?php $is_logged_in = isset($is_logged_in) ? $is_logged_in : FALSE; ?>
<!doctype html>
<!-- paulirish.com/2008/conditional-stylesheets-vs-css-hacks-answer-neither/ -->
<!--[if lt IE 7]> <html class="no-js lt-ie9 lt-ie8 lt-ie7" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="no-js lt-ie9 lt-ie8" lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="no-js lt-ie9" lang="en"> <![endif]-->
<!-- Consider adding a manifest.appcache: h5bp.com/d/Offline -->
<!--[if gt IE 8]><!--> <html class="no-js" lang="en"> <!--<![endif]-->
<head>
  <meta charset="utf-8">

  <!-- Use the .htaccess and remove these lines to avoid edge case issues.
       More info: h5bp.com/i/378 -->
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

  <title><?php echo $title; ?></title>
  <meta name="description" content="">

  <!-- Mobile viewport optimized: h5bp.com/viewport -->
  <meta name="viewport" content="width=device-width">

  <!-- Place favicon.ico and apple-touch-icon.png in the root directory: mathiasbynens.be/notes/touch-icons -->

<!--   <link href="http://essentials3.net/cache/doc/assets/css/ess1/jquery-ui-1.8.13.custom.css" rel="stylesheet" type="text/css" />
  <link href="http://essentials3.net/cache/doc/assets/css/style.css" rel="stylesheet" type="text/css" />
 -->

  <?php echo link_tag("assets/css/ess1/jquery-ui-1.8.13.custom.css"); ?>
  <?php echo link_tag("assets/css/style.css"); ?>

  <!-- More ideas for your <head> here: h5bp.com/d/head-Tips -->

  <!-- All JavaScript at the bottom, except this Modernizr build.
       Modernizr enables HTML5 elements & feature detects for optimal performance.
       Create your own custom Modernizr build: www.modernizr.com/download/ -->
<!--  <script src="http://essentials3.net/cache/doc/assets/js/libs/modernizr-2.5.2.min.js"></script>-->
	<script src="<?php echo base_url(); ?>assets/js/libs/modernizr-2.6.2.min.js"></script>
</head>
<body>
  <!-- Prompt IE 6 users to install Chrome Frame. Remove this if you support IE 6.
       chromium.org/developers/how-tos/chrome-frame-getting-started -->
  <!--[if lt IE 7]><p class=chromeframe>Your browser is <em>ancient!</em> <a href="http://browsehappy.com/">Upgrade to a different browser</a> or <a href="http://www.google.com/chromeframe/?redirect=true">install Google Chrome Frame</a> to experience this site.</p><![endif]-->
  <header>

  </header>
  <div role="main">
    <?php echo $is_logged_in ? anchor('logout', 'Logout', array('id' => 'logout-link')) : ''; ?>
    <input type="hidden" id="base-url" value="<?php echo base_url(); ?>" />
    <?php $this->load->view($view); ?>
  </div>
  <footer style="width:750px; margin-left: auto; margin-right: auto;">

<script type="text/javascript">
google_ad_client = "ca-pub-0790686144654653";
/* KH */
google_ad_slot = "1891405969";
google_ad_width = 728;
google_ad_height = 90;
</script>
<script type="text/javascript"
src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script>


  </footer>


  <!-- JavaScript at the bottom for fast page loading -->

  <!-- Grab aspnet CDN's jQuery, with a protocol relative URL; fall back to local if offline -->
  <script src="//ajax.aspnetcdn.com/ajax/jquery/jquery-1.9.0.min.js"></script>
  <script>window.jQuery || document.write('<script src="<?php echo base_url('assets/js/libs/jquery-1.9.0.min.js'); ?>"><\/script>')</script>
  <script src="//ajax.aspnetcdn.com/ajax/jquery.ui/1.10.0/jquery-ui.min.js"></script>
  <script>window.jQuery.ui || document.write('<script src="<?php echo base_url('assets/js/libs/jquery-ui-1.10.0.custom.min.js'); ?>"<\/script>')</script>

  <!-- Add the Datatables library -->
  <script src="//ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.0/jquery.dataTables.min.js"></script>
  <?php if($is_logged_in) : ?>
  <script src="<?php echo base_url(); ?>assets/js/libs/jquery.jeditable.mini.js"></script>
  <?php endif; ?>

  <!-- scripts concatenated and minified via build script -->
  <script src="<?php echo base_url('assets/js/plugins.js'); ?>"></script>
  <script src="<?php echo base_url('assets/js/mylibs/scripts.js'); ?>"></script>
  <!-- end scripts -->

</body>
</html>
