import 'jquery-ui-dist/jquery-ui'
import Dropzone from 'dropzone'

// Function to initialize elements with class render-json
const initializeRenderJson = (container) => {
  console.log('initializing render-json elements')
  const jsonElements = container.querySelectorAll('.render-json')
  jsonElements.forEach((element) => {
    const jsonData = JSON.parse(element.textContent)
    const dataLevel = element.getAttribute('data-level') || 1  // Default to 1 if attribute is not set
    element.innerHTML = '' // Clear the current text content
    element.appendChild(renderjson
      .set_sort_objects(true)
      .set_show_to_level(Number(dataLevel)) // Convert to number just to be sure
      .set_max_string_length(50)
      (jsonData)
    )
  })
}


// Function to initialize dropzones
const initializeDropzones = (container) => {
  console.log('initializing dropzones')
  const dropzoneForms = container.querySelectorAll('form.e-doc.dropzone')
  dropzoneForms.forEach((form) => {
    new Dropzone(form, {
      paramName: "file",
      maxFilesize: 100,
      acceptedFiles: "image/*, application/pdf, .psd, .xml",
      addRemoveLinks: true,
      init: function() {
        this.on("success", function(file, responseText) {
          const containerId = form.getAttribute('data-container-id')
          updateContainer(containerId, responseText)
        })
      }
    })
  })
}

// Function to initialize eDoc delete buttons
const initializeDeleteButtons = (container) => {
  console.log('initializing eDoc delete buttons')
  container.querySelectorAll('.delete-eDoc').forEach(button => {
    button.addEventListener('click', function() {
      if (!confirm('Are you sure you want to delete this eDoc?')) return
      const eDocId = this.getAttribute('data-edoc-id')
      const csrfToken = this.getAttribute('data-csrf-token')
      const containerId = this.getAttribute('data-container-id')
      const url = `/admin/eDocs/${eDocId}`  // Make sure the URL matches your route definition

      fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': csrfToken
        }
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateContainer(containerId, data.updatedHTML)
          } else {
            console.log('Error deleting eDoc')
          }
        })
    })
  })
}

// Function to update a container by its ID with new HTML content
const updateContainer = (containerId, updatedHTML) => {
  const containerElement = document.getElementById(containerId)
  console.log('containerElement: ' + containerElement)
  if (containerElement) {
    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = updatedHTML
    const newContainerElement = tempDiv.firstElementChild // Change this line

    // Replace the existing container with the new one
    containerElement.parentNode.replaceChild(newContainerElement, containerElement)

    // Re-initialize dropzones and delete buttons in the updated container
    initializeDropzones(newContainerElement) // Pass the new container element
    initializeDeleteButtons(newContainerElement) // Pass the new container element
  }
}


// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  console.log('DOM is ready')
  initializeRenderJson(document)
  initializeDropzones(document)
  initializeDeleteButtons(document)

  const cardHeaders = document.querySelectorAll('.card.collapsible .card-header')

  cardHeaders.forEach(header => {
    header.addEventListener('click', function() {
      const card = this.closest('.card')
      const cardBody = this.nextElementSibling

      if (cardBody.style.display === 'none') {
        cardBody.style.display = ''
        card.classList.remove('collapsed')
      } else {
        cardBody.style.display = 'none'
        card.classList.add('collapsed')
      }
    })
  })

  console.log('Adding click event listeners')
  document.querySelectorAll('.copy-to-clipboard').forEach(function(element) {
    console.log('Adding click event listener to copy-to-clipboard element')
    element.addEventListener('click', function(event) {
      event.preventDefault()
      const textToCopy = document.querySelector(this.dataset.target).innerText
      navigator.clipboard.writeText(textToCopy).then(() => {
        // Show the popup
        const popup = document.getElementById('clipboard-popup')
        popup.style.display = 'block'

        // Hide the popup after 3 seconds (3000 milliseconds)
        setTimeout(function() {
          popup.style.display = 'none'
        }, 3000)
      }).catch(err => {
        alert('Failed to copy text')
      })
    })
  })

  console.log('initialized dropzones and delete buttons')
})
