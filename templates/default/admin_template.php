<!DOCTYPE html>
<html lang="<?php echo $settings['lang']; ?>">
<head>
 <title>Administration | <?php echo $settings['title']; ?></title>
  <link rel="stylesheet" href="templates/default/gallery.css">
  <style type="text/css">
   #Loader {display:none;position:absolute;top:140px;left:20%;width:50%;height:50%;font-size:3em;text-align:center;background:rgba(243,243,250,0.9);border-radius:15px;}
   #Loader .text {position:relative;top:36%;}
  </style>
</head>
<body>
  <header id="site_header">
   <h1><?php echo $settings['title']; ?></h1>
   <nav>
    <ol>
     <li><a href="/">HOME</a></li>
    </ol>
   </nav>
   <div class="clear"></div>
  </header>
  <article>
    <form action="upload.php" method="post" enctype="multipart/form-data" id="uploadForm">
      <label>Upload file:</label>
      <input type="file" name="fileToUpload" id="fileToUpload">
      <label>Place in category:</label>
      <?php echo $select_category; ?>
      <button class="button" onClick="uploadFile()" type="button">Upload</button>
    </form>
  </article>
  <footer>
   <nav>
    <ol>
     <li><a href="admin.php">Administration</a></li>
    </ol>
   </nav>
  </footer>
  <div id="Loader"><div class="text"><p>Uploader...</p><div>&#x279f;&#x279f;</div></div></div>
  <script type="text/javascript">
  function uploadFile() {
	document.getElementById('Loader').style.display = "block";
    setTimeout(ulFormSubmit, 1000);
  }
  function ulFormSubmit() {
	document.getElementById('uploadForm').submit();
  }
  </script>
</body>
</html>