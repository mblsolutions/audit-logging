## v2.1.0

+ Add CloudWatch and stack drivers
+ Add route, duration, user agent, request id, trace id and app context to the DTO
+ Fill in missing config keys and drivers from the package config
+ Always mask auth and cookie headers
+ Mask protected keys in any case, in the URI and in JSON with escaped quotes
+ Retry audit jobs 5 times and log failures without bodies
+ Don't break the request when audit logging fails
+ Accept "1" for AUDIT_LOGGING_ENABLED

## v2.0.1

+ AB#119420 Fix the request and response header in DTO

## v2.0.0

+ AB#114580 Upgrade Laravel to v12.

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
