## Nexcessnet_Turpentine Varnish v3 VCL Template

## Backends

#{{backend}}
backend default {
  .host = "127.0.0.1";
  .port = "80";
}

## ACLs

#{{acls}}
acl purge-trusted {
    "127.0.0.1";
}

## Custom Subroutines
#https://www.varnish-cache.org/trac/wiki/VCLExampleNormalizeUserAgent
sub normalize_user_agent {
    if (req.http.user-agent ~ "MSIE") {
        set req.http.X-UA = "msie";
    } else if (req.http.user-agent ~ "Firefox") {
        set req.http.X-UA = "firefox";
    } else if (req.http.user-agent ~ "Safari") {
        set req.http.X-UA = "safari";
    } else if (req.http.user-agent ~ "Chrome") {
        set req.http.X-UA = "chrome";
    } else if (req.http.user-agent ~ "Opera Mini/") {
        set req.http.X-UA = "opera-mini";
    } else if (req.http.user-agent ~ "Opera Mobi/") {
        set req.http.X-UA = "opera-mobile";
    } else if (req.http.user-agent ~ "Opera") {
        set req.http.X-UA = "opera";
    } else {
        set req.http.X-UA = "nomatch";
    }
}

#https://www.varnish-cache.org/trac/wiki/VCLExampleNormalizeAcceptEncoding
sub normalize_encoding {
    if (req.http.Accept-Encoding) {
        if (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } else if (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            # unkown algorithm
            remove req.http.Accept-Encoding;
        }
    }
}

## Varnish Subroutines

sub vcl_recv {
    if (req.restarts == 0) {
        if (req.http.x-forwarded-for) {
            set req.http.X-Forwarded-For =
                req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }
    if (req.request == "PURGE") {
        if (!client.ip ~ purge-trusted) {
            error 405 "Not Allowed";
        } else {
            ban_url(req.url);
            error 200 "Purged: " + req.url;
        }
    }
    if (req.request != "GET" &&
            req.request != "HEAD" &&
            req.request != "PUT" &&
            req.request != "POST" &&
            req.request != "TRACE" &&
            req.request != "OPTIONS" &&
            req.request != "DELETE") {
        /* Non-RFC2616 or CONNECT which is weird. */
        return (pipe);
    }
    if (req.request != "GET" && req.request != "HEAD") {
        /* We only deal with GET and HEAD by default */
        return (pass);
    }
    if (req.url ~ "^/(?:(?:index|litespeed)\.php/)?{{admin_name}}") {
        return (pass);
    }

    call normalize_encoding;
    call normalize_user_agent;

    if (req.http.cookie ~ "varnish_nocache") {
        return (pass);
    } else {
        unset req.http.Cookie;
        return (lookup);
    }
}

sub vcl_pipe {
    set req.http.connection = "close";
    return (pipe);
}

# sub vcl_pass {
#     return (pass);
# }

sub vcl_hash {
    hash_data(req.url);
    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }
    if (req.http.X-UA) {
        hash_data(req.http.X-UA);
    }
    if (req.http.Accept-Encoding) {
        hash_data(req.http.Accept-Encoding);
    }
    return (hash);
}

# sub vcl_hit {
#     return (deliver);
# }
#
# sub vcl_miss {
#     return (fetch);
# }
#
sub vcl_fetch {
    if (beresp.http.Set-Cookie ~ "varnish_nocache=1") {
        return (deliver);
    }
}
# sub vcl_fetch {
#     if (beresp.ttl <= 0s ||
#         beresp.http.Set-Cookie ||
#         beresp.http.Vary == "*") {
# 		/*
# 		 * Mark as "Hit-For-Pass" for the next 2 minutes
# 		 */
# 		set beresp.ttl = 120 s;
# 		return (hit_for_pass);
#     }
#     return (deliver);
# }
#
# sub vcl_deliver {
#     return (deliver);
# }
#
# sub vcl_error {
#     set obj.http.Content-Type = "text/html; charset=utf-8";
#     set obj.http.Retry-After = "5";
#     synthetic {"
# <?xml version="1.0" encoding="utf-8"?>
# <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
#  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
# <html>
#   <head>
#     <title>"} + obj.status + " " + obj.response + {"</title>
#   </head>
#   <body>
#     <h1>Error "} + obj.status + " " + obj.response + {"</h1>
#     <p>"} + obj.response + {"</p>
#     <h3>Guru Meditation:</h3>
#     <p>XID: "} + req.xid + {"</p>
#     <hr>
#     <p>Varnish cache server</p>
#   </body>
# </html>
# "};
#     return (deliver);
# }
#
# sub vcl_init {
# 	return (ok);
# }
#
# sub vcl_fini {
# 	return (ok);
# }
