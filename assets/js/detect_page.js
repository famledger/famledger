import Dropzone from 'dropzone';

const initializeDocumentDetectorDropzone = () => {
  console.log('initializing Document Detector dropzone');

  const form = document.querySelector('form.document-detector-dropzone');
  const formAction = form.getAttribute('action');

  new Dropzone(form, {
    url: formAction,
    paramName: "file",
    maxFilesize: 20,
    acceptedFiles: ".pdf,.txt,.xml,application/pdf,text/plain,application/xml",
    addRemoveLinks: true,
    init: function() {
      this.on("success", function(file, responseText) {
        console.log('File upload was successful, updating protocol');
        // You could update the protocol display here, using `responseText` that you got back from the server
        document.querySelector('#document-detector-protocol').innerHTML = responseText;
        this.removeAllFiles();
      });
    }
  });
};

document.addEventListener('DOMContentLoaded', function() {
  initializeDocumentDetectorDropzone();
});
