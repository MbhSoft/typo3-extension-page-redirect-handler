# Page Redirect Handler

## Usage

```yml
errorHandling:
  -
    errorCode: 401
    errorHandler: PHP
    errorPhpClassFQCN: MbhSoftware\PageRedirectHander\Error\PageErrorHandler\PageRedirectErrorHandler
    errorRedirectTarget: 't3://page?uid=42'
    errorRedirectTargetStatuscode: 307 #optional - 307 is default
    errorRedirectAdditionalParameters: #optional
      return_url: '###CURRENT_URL###'
