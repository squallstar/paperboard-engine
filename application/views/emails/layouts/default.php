<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0"/>

	<style type="text/css">

		body {
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			width: 100%;
			max-width: 100%;
			font-size: 17px;
			line-height: 24px;
			color: #42464D;
			background: #F9F9F9;
			text-shadow: 0 1px 1px rgba(255,255,255,0.75), 0 1px 1px white;
		}

		table {
			width: 100%;
			margin: 0 auto;
		}

		h1, h2, h3, h4 {
			color: #2ab27b;
			margin-bottom: 12px;
			line-height: 26px;
		}

		p, ul, ul li {
			font-size: 17px;
			margin: 0 0 16px;
			line-height: 24px;
		}

		p.mini {
			font-size: 12px;
			line-height: 18px;
			color: #ABAFB4;
		}

		p.message {
			font-size: 16px;
			line-height: 20px;
			margin-bottom: 4px;
		}

		hr {
			margin: 2rem auto;
			width: 50%;
			border: none;
			border-bottom: 1px solid #DDD;
		}

		a, a:link, a:visited, a:active, a:hover {
			font-weight: bold;
			color: #439fe0;
			text-decoration: none;
			word-break: break-word;
		}
		.time {
			font-size: 11px;
			color: #ABAFB4;
			padding-right: 6px;
		}
		.emoji {
			vertical-align: bottom;
		}
		.avatar {
			border-radius: 2px;
		}
		#footer p {
			margin-top: 16px;
			font-size: 12px;
		}

		/* Client-specific Styles */
		#outlook a {padding:0;}
		body{width:100% !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; margin:0 auto; padding:0;}
		.ExternalClass {width:100%;}
		.ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {line-height: 100%;}
		#backgroundTable {margin:0; padding:0; width:100%; line-height: 100% !important;}

		/* Some sensible defaults for images
		Bring inline: Yes. */
		img {outline:none; text-decoration:none; -ms-interpolation-mode: bicubic;}
		a img {border:none;}
		.image_fix {display:block;}

		/* Outlook 07, 10 Padding issue fix
		Bring inline: No.*/
		table td {border-collapse: collapse;}

	    /* Fix spacing around Outlook 07, 10 tables
	    Bring inline: Yes */
	    table { border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; }

		/* Mobile */
		@media only screen and (max-device-width: 480px) {
			/* Part one of controlling phone number linking for mobile. */
			a[href^="tel"], a[href^="sms"] {
						text-decoration: none;
						color: blue; /* or whatever your want */
						pointer-events: none;
						cursor: default;
					}

			.mobile_link a[href^="tel"], .mobile_link a[href^="sms"] {
						text-decoration: default;
						color: orange !important;
						pointer-events: auto;
						cursor: default;
					}

		}

		/* More Specific Targeting */
		@media only screen and (min-device-width: 768px) and (max-device-width: 1024px) {
		/* You guessed it, ipad (tablets, smaller screens, etc) */
			/* repeating for the ipad */
			a[href^="tel"], a[href^="sms"] {
						text-decoration: none;
						color: blue; /* or whatever your want */
						pointer-events: none;
						cursor: default;
					}

			.mobile_link a[href^="tel"], .mobile_link a[href^="sms"] {
						text-decoration: default;
						color: orange !important;
						pointer-events: auto;
						cursor: default;
					}
		}

		/* iPhone Retina */
		@media only screen and (-webkit-min-device-pixel-ratio: 2) and (max-device-width: 640px)  {
			/* Must include !important on each style to override inline styles */
			#footer p {
				font-size: 9px;
			}
		}

		/* Android targeting */
		@media only screen and (-webkit-device-pixel-ratio:.75){
			/* Put CSS for low density (ldpi) Android layouts in here */
			img {
				max-width: 100%;
				height: auto;
			}
		}
		@media only screen and (-webkit-device-pixel-ratio:1){
			/* Put CSS for medium density (mdpi) Android layouts in here */
			img {
				max-width: 100%;
				height: auto;
			}
		}
		@media only screen and (-webkit-device-pixel-ratio:1.5){
			/* Put CSS for high density (hdpi) Android layouts in here */
			img {
				max-width: 100%;
				height: auto;
			}
		}
		/* Galaxy Nexus */
		@media only screen and (min-device-width : 720px) and (max-device-width : 1280px) {
			img {
				max-width: 100%;
				height: auto;
			}
			body {
				font-size: 16px;
			}
		}
		/* end Android targeting */


	</style>
</head>
<body>
	<table width="100%" cellpadding="0" cellspacing="0" border="0" id="backgroundTable" style="font-size: 17px; line-height: 24px; color: #42464D; background: #F9F9F9; text-shadow: 0 1px 1px rgba(255,255,255,0.75), 0 1px 1px white;">
		<tr>
			<td valign="top">
				<table id="header" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: white; border-bottom: 1px solid #DDDDDD;">
					<tr>
						<td valign="bottom" style="padding: 20px 16px 16px;">
							<div style="max-width: 600px; margin: 0 auto;">
								<a href="#">
									<img src="<?php echo base_url('assets/images/email/logo.png'); ?>" style="width: 50px; height: 44px;" />
								</a>
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<tr>
			<td valign="top">
				<table id="body" width="100%" cellpadding="0" cellspacing="0" border="0" align="center">
					<tr>
						<td valign="top">
							<div style="max-width: 600px; margin: 1.7rem auto 0; padding: 0 16px;">
								<?php $this->load->view($_template); ?>
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<tr>
			<td>
				<table id="footer" width="100%" cellpadding="0" cellspacing="0" border="0" align="center" style="margin-top: 3rem; background: white; color: #989EA6;">
					<tr>
						<td style="height: 3px; background-image: url('https://slack.global.ssl.fastly.net/22423/img/email-ribbon_@2x.png'); background-repeat: repeat-x; background-size: auto 5px;"></td>
					</tr>
					<tr>
						<td valign="top" align="center" style="padding: 16px 8px 64px;">
							<div style="max-width: 600px; margin: 0 auto;">
								<p class="footer_address" style="margin-top: 16px; font-size: 12px; line-height: 20px;">
									Made with love by <a href="#" style="font-weight: bold; color: #439fe0;">Paperboard</a> in London &nbsp;&bull;&nbsp;
									<a href="https://squallstar.it" style="font-weight: bold; color: #439fe0;">Squallstar Studio</a><br />
									Connect &nbsp;&bull;&nbsp; Discover &nbsp;&bull;&nbsp; Curate<br />
								</p>
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>

	</table>
</body>
</html>