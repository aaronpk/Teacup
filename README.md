# Teacup

## Routes

### / 

Teacup is a simple app for tracking what you are drinking.

You can post what you're drinking to your own site, or you can post to an account provided by Teacup.


### /auth/start

Copy from Quill.

Discover IndieAuth + Micropub endpoints.

#### Authorize

If a Micropub endpoint is found, show a message with a button to start the authorization flow. 

Also provide a button to create an account in case they don't want to use their own site.

#### Create Account

Show a message and provide a button to create an account. 

Starts authentication with indieauth.com using the authenication flow.

### /auth/callback

Copy from Quill up to line 200.

If a token endpoint is found, get an access token from it.

If no token endpoint is found, verify the code with indieauth.com and create an account for the user.

### /post/new

The signed-in view used to post new content.

Show the list of drinks that can be posted.

### /post/submit

The form submits here. Saves the post in the database, then tries to make a micropub request if necessary. If the micropub request succeeds, updates the post with the canonical URL in the response.

### /{domain}

Show feed of the user's recent posts. Posts include a link to the canonical URL if appropriate.


### /signout

Destroy session.




## Contributing

By contributing to this project, you agree to irrevocably release your contributions under the same license as this project.


## Credits 




## License

Copyright 2013 by Aaron Parecki

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
