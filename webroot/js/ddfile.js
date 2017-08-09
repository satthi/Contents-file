(function($) {

  /*
  // プラグイン制作の参考
  http://cartman0.hatenablog.com/entry/2015/06/08/131855
  */

  // dataTransferをevent内の属性に含める
  // http://qiita.com/sengok/items/5cbe4cd32a17bbaea7eb
  $.event.props.push("dataTransfer");

  $.fn.ddfile = function(settings) {
    settings = jQuery.extend({
      uploadFileSizeLimit: 2 * 1024 * 1024,
      imgBlock: null,
      dataInput: null,
      fileInput: null,
      dropZone: null,
      fileName: null,
      fileNameHidden: null
    },settings);

    var imgBlockDom,dataInputDom,fileInputDom,dropZoneDom,fileNameDom,fileNameHiddenDom;
    if (!settings.imgBlock || !settings.dataInput || !settings.fileInput || !settings.dropZone || !settings.fileNameHidden) {
      alert('必要な設定が不足しています');
      return;
    }
    imgBlockDom = $(settings.imgBlock);
    dataInputDom = $(settings.dataInput);
    fileInputDom = $(settings.fileInput);
    dropZoneDom = $(settings.dropZone);
    if (settings.fileName) {
      fileNameDom = $(settings.fileName);
    }
    fileNameHiddenDom = $(settings.fileNameHidden);

    if (checkFileApi()){
      //ファイル選択
      fileInputDom.on('change', selectReadfile);
      //ドラッグオンドロップ
      dropZoneDom.on('dragover', handleDragOver);
      dropZoneDom.on('drop', handleDragDropFile);
    }

    // FileAPIに対応しているか
    function checkFileApi() {
      // Check for the various File API support.
      if (window.File && window.FileReader && window.FileList && window.Blob) {
        // Great success! All the File APIs are supported.
        return true;
      }
      alert('The File APIs are not fully supported in this browser.');
      return false;
    }

    //ファイルが選択されたら読み込む
    function selectReadfile(e) {
      var file = e.target.files[0];
      readImage(file);
    }

    //ドラッグオンドロップ
    function handleDragOver(e) {
      e.stopPropagation();
      e.preventDefault();
      e.dataTransfer.dropEffect = 'copy'; // Explicitly show this is a copy.
    }

    function handleDragDropFile(e) {
      e.stopPropagation();
      e.preventDefault();
      var files = e.dataTransfer.files; // FileList object.
      var file = files[0];
      readImage(file);
    }

    //ファイルの読み込み処理
    function readImage(file){
      var filename = file.name;
      // ファイルのアップロード上限の設定
      if (file.size > settings.uploadFileSizeLimit) {
        alert('ファイルのアップロード上限を超えています');
        return;
      }
      var reader = new FileReader();
      //dataURL形式でファイルを読み込む
      reader.readAsDataURL(file);
      //ファイルの読込が終了した時の処理
      reader.onload = function(){
        //ファイル読み取り後の処理
        var result_DataURL = reader.result;
        //読み込んだ画像とdataURLを書き出す
        var img = document.getElementById('image');
        var src = document.createAttribute('src');
        src.value = result_DataURL;
        img.setAttributeNode(src);
        dataInputDom.val(result_DataURL);
        $('#example-text').hide();
        if (typeof fileNameDom !== "undefined") {
          fileNameDom.html(filename);
        }
        console.log(fileNameHiddenDom);
        fileNameHiddenDom.val(filename);
      }

    }
  }
})(jQuery);
