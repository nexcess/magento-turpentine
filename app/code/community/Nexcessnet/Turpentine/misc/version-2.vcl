## Nexcessnet_Turpentine Varnish v2 VCL Template

## Backends

{{default_backend}}

{{admin_backend}}

## ACLs

{{crawler_acl}}

acl local_ip {
    "127.0.0.1";
}

## Custom Subroutines
sub remove_cache_headers {
    remove beresp.http.Set-Cookie;
    remove beresp.http.Cache-Control;
    remove beresp.http.Expires;
    remove beresp.http.Pragma;
    remove beresp.http.Cache;
    remove beresp.http.Age;
}

sub remove_double_slashes {
    set req.url = regsub(req.url, "(.*)//+(.*)", "\1/\2");
}

sub set_fake_esi_level {
    if (req.url ~ "{{url_base_regex}}turpentine/esi/getBlock/") {
        set req.http.X-Varnish-Esi-Level = "1";
    } else {
        remove req.http.X-Varnish-Esi-Level;
    }
}

sub handle_req_cookie {
    if (!req.http.X-Varnish-Esi-Level && req.http.X-Varnish-Cookie) {
        remove req.http.Cookie;
    } else if (req.http.X-Varnish-Esi-Level && req.http.X-Varnish-Cookie) {
        set req.http.Cookie = req.http.X-Varnish-Cookie;
    }
}

## Varnish Subroutines

sub vcl_recv {
    if (req.restarts == 0) {
        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For =
                req.http.X-Forwarded-For ", " client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    if (req.request != "GET" &&
            req.request != "HEAD" &&
            req.request != "PUT" &&
            req.request != "POST" &&
            req.request != "TRACE" &&
            req.request != "DELETE" &&
            req.request != "OPTIONS") {
        /* Non-RFC2616 or CONNECT which is weird. */
        return (pipe);
    }

    if (req.request != "GET" && req.request != "HEAD") {
        /* We only deal with GET and HEAD by default */
        return (pass);
    }

    call remove_double_slashes;

    {{normalize_encoding}}
    {{normalize_user_agent}}
    {{normalize_host}}

    set req.http.X-Opt-Enable-Caching = "{{enable_caching}}";
    set req.http.X-Opt-Force-Static-Caching = "{{force_cache_static}}";
    set req.http.X-Opt-Enable-Get-Excludes = "{{enable_get_excludes}}";

    if (req.http.X-Opt-Enable-Caching !~ "true") {
        return (pipe);
    }
    if (req.url ~ "{{url_base_regex}}{{admin_frontname}}") {
        set req.backend = admin;
        return (pipe);
    }

    call set_fake_esi_level;

    if (req.http.X-Varnish-Esi-Level && client.ip ~ local_ip) {
        error 403 "External ESI requests are not allowed";
    }
    if (req.url ~ "{{url_base_regex}}") {
        if (req.http.Cookie ~ "frontend=") {
            set req.http.X-Varnish-Cookie = req.http.Cookie;
        } else {
            if (client.ip ~ crawler_acl) {
                set req.http.Cookie = "frontend=no-session";
                set req.http.X-Varnish-Cookie = req.http.Cookie;
            } else {
                #pass so we can get a unique session
                return (pass);
            }
        }
        if (req.http.X-Opt-Force-Static-Caching ~ "true" &&
                req.url ~ ".*\.(?:{{static_extensions}})(?=\?|$)") {
            remove req.http.Cookie;
            return (lookup);
        }
        if (req.url ~ "{{url_base_regex}}(?:{{url_excludes}})") {
            return (pass);
        }
        if (req.http.X-Opt-Enable-Get-Excludes ~ "true" &&
                req.url ~ "(?:[?&](?:{{get_param_excludes}})(?=[&=]|$))") {
            return (pass);
        }
        return (lookup);
    }
    # else it's not part of magento so do default handling (doesn't help
    # things underneath magento but we can't detect that)
}

sub vcl_pipe {
    set req.http.Connection = "close";
    return (pipe);
}

sub vcl_pass {
    call handle_req_cookie;
    return (pass);
}

sub vcl_hash {
    set req.hash += req.url;
    if (req.http.Host) {
        set req.hash += req.http.Host;
    } else {
        set req.hash += server.ip;
    }
    if (req.http.X-Normalized-User-Agent) {
        set req.hash += req.http.X-Normalized-User-Agent;
    }
    if (req.http.Accept-Encoding) {
        set req.hash += req.http.Accept-Encoding;
    }
    if (req.http.X-Varnish-Esi-Level) {
        if (req.url ~ "/cacheType/per-client/" && req.http.Cookie ~ "frontend=") {
            set req.hash += regsub(req.http.Cookie, "^.*?frontend=([^;]*);*.*$", "\1");
        }
    }
    return (hash);
}

# sub vcl_hit {
#     return (deliver);
# }

sub vcl_miss {
    call handle_req_cookie;
    return (fetch);
}

sub vcl_fetch {
    set req.grace = {{grace_period}}s;

    if (beresp.status != 200 && beresp.status != 404) {
        set beresp.ttl = {{grace_period}}s;
        return (pass);
    } else {
        if (beresp.http.Set-Cookie) {
            set beresp.http.X-Varnish-Set-Cookie = beresp.http.Set-Cookie;
            remove beresp.http.Set-Cookie;
        }
        if (!req.http.X-Varnish-Esi-Level &&
                req.http.X-Varnish-Cookie !~ "frontend=" &&
                !(client.ip ~ crawler_acl)) {
            set beresp.http.X-Varnish-Use-Set-Cookie = "1";
        }
        if (beresp.http.X-Turpentine-Esi ~ "1") {
            esi;
        }
        if (beresp.http.X-Turpentine-Cache ~ "0") {
            set beresp.cacheable = false;
            set beresp.ttl = {{grace_period}}s;
            return (pass);
        } else {
            set beresp.cacheable = true;
            #TODO: only remove the User-Agent field from this if it exists
            remove beresp.http.Vary;
            if (req.http.X-Opt-Force-Static-Caching ~ "true" &&
                    bereq.url ~ ".*\.(?:{{static_extensions}})(?=\?|$)") {
                call remove_cache_headers;
                set beresp.ttl = {{static_ttl}}s;
            } else if (req.http.X-Varnish-Esi-Level) {
                call remove_cache_headers;
                if (req.url ~ "/cacheType/per-client/" &&
                        req.http.Cookie ~ "frontend=") {
                    set beresp.http.X-Varnish-Session = regsub(req.http.Cookie,
                        "^.*?frontend=([^;]*);*.*$", "\1");
                }
                set beresp.ttl = regsub(req.url, ".*/ttl/([0-9]+)/.*","\1s");
            } else {
                call remove_cache_headers;
                {{url_ttls}}
            }
        }
    }
    return (deliver);
}

#https://www.varnish-cache.org/trac/wiki/VCLExampleHitMissHeader
sub vcl_deliver {
    if (resp.http.X-Varnish-Use-Set-Cookie) {
        set resp.http.Set-Cookie = resp.http.X-Varnish-Set-Cookie;
    }
    set resp.http.X-Opt-Debug-Headers = "{{debug_headers}}";
    if (resp.http.X-Opt-Debug-Headers ~ "true") {
        set resp.http.X-Varnish-Hits = obj.hits;
    } else {
        #remove Varnish fingerprints
        remove resp.http.X-Varnish;
        remove resp.http.Via;
        remove resp.http.X-Powered-By;
        remove resp.http.Server;
        remove resp.http.Age;
        remove resp.http.X-Turpentine-Cache;
        remove resp.http.X-Turpentine-Esi;
        remove resp.http.X-Varnish-Session;
        remove resp.http.X-Varnish-Set-Cookie;
        remove resp.http.X-Varnish-Use-Set-Cookie;
    }
    remove resp.http.X-Opt-Debug-Headers;
}
