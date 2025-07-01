## v1.6.1

+ AB#76543 Add user id to provide key for users table correlation in RequestResponseDTO

## v1.6.0

+ AB#44278 Add optional migration and command for archiving

## v1.5.0

+ AB#44278 Add optional trait and model for retrieving audit logs
+ Add support for Laravel 11

## v1.4.1

+ Add stripslashes to json decode so will result in array instead of string

## v1.4.0

+ Update dependencies for laravel v10

## v1.3.0

+ Remove Authenticatable typehint from RequestResponseDTO

## v1.2.0

+ Mask sensitive data trait update

## v1.1.0

+ Log the route binding parameters that are being passed e.g. User ID, Order ID etc...

## v1.0.0

+ Add DTO to assist with job data serialisation
+ Separate middlewares into types

## v0.2.0

+ Dispatch audit logging service to a job (to enable queueing)

## v0.1.0

+ initial pre-release