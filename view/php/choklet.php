<!DOCTYPE html >
<html prefix="og: http://ogp.me/ns#" lang="en">
<head>
  <title><?php if(!empty($page['title'])) echo $page['title']; ?></title>
  <script>let baseurl="<?php echo z_root(); ?>";</script>
  <?php if(!empty($page['htmlhead'])) echo $page['htmlhead'] ?>
</head>
<body>
	<div id="blog-margin">
		<header><?php if(!empty($page['header'])) echo $page['header']; ?></header>
		<nav class="navbar navbar-inverse navbar-fixed-top" role="navigation"><?php if(!empty($page['nav'])) echo $page['nav']; ?></nav>
		<div id="nav-backer" class="navbar">&nbsp;</div>
		<div id="blog-banner"><?php if(!empty($page['banner'])) echo $page['banner']; ?></div>
		<main>
		<aside id="region_1"><?php if(!empty($page['aside'])) echo $page['aside']; ?></aside>
		<section id="region_2"><?php if(!empty($page['content'])) echo $page['content']; ?>
			<div id="page-footer"></div>
			<div id="pause"></div>
		</section>
		<aside id="region_3"><?php if(!empty($page['right_aside'])) echo $page['right_aside']; ?></aside>
		</main>
		<div class="clear"></div>
		<footer><?php if(!empty($page['footer'])) echo $page['footer']; ?></footer>
	</div>
</body>
</html>

