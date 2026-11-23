/**
 * Utility function to download a report
 *
 * @param fileData The binary report data returned by the server
 * @param fileName Default name of the file when downloaded
 */
export default function downloadReport(fileData: Blob, fileName: string) {
  // Create the download link
  const url = window.URL.createObjectURL(fileData)
  const link = document.createElement('a')

  link.href = url
  link.setAttribute('download', fileName)

  // Trigger download then clean up
  document.body.appendChild(link)
  link.click()
  link.parentNode?.removeChild(link)
  window.URL.revokeObjectURL(url)
}
