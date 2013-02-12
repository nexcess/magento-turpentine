## Nexcessnet_Turpentine Varnish v3 VCL Template

## Imports

import std;

## Custom VCL Logic

{{custom_vcl_include}}

## Backends

{{default_backend}}

{{admin_backend}}

## ACLs

{{crawler_acl}}

## Custom Subroutines

sub remove_cache_headers {
    unset beresp.http.Cache-Control;
    unset beresp.http.Expires;
    unset beresp.http.Pragma;
    unset beresp.http.Cache;
    unset beresp.http.Age;
}

sub remove_double_slashes {
    set req.url = regsub(req.url, "(.*)//+(.*)", "\1/\2");
}

## Varnish Subroutines

sub vcl_recv {
    if (req.restarts == 0) {
        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For =
                req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    set req.http.X-Turpentine-Secret-Handshake = "{{secret_handshake}}";

    if (req.request !~ "^(GET|HEAD|PUT|POST|TRACE|DELETE|OPTIONS)$") {
        # Non-RFC2616 or CONNECT which is weird.
        return (pipe);
    }

    if (req.request !~ "^(GET|HEAD)$") {
        # We only deal with GET and HEAD by default
        return (pipe);
    }

    call remove_double_slashes;

    {{normalize_encoding}}
    {{normalize_user_agent}}
    {{normalize_host}}

    #GCC should completely optimize any "false && <cond>" branches away, hopefully
    if (!{{enable_caching}} || req.http.Authorization) {
        return (pipe);
    }
    if (req.url ~ "{{url_base_regex}}{{admin_frontname}}") {
        set req.backend = admin;
        return (pipe);
    }
    if (req.url ~ "{{url_base_regex}}turpentine/esi/getBlock/" &&
            req.url ~ "/{{esi_method_param}}/esi/" &&
            req.esi_level == 0) {
        error 403 "External ESI requests are not allowed";
    }
    if (req.url ~ "{{url_base_regex}}") {
        if (req.http.Cookie ~ "frontend=") {
            set req.http.X-Varnish-Cookie = req.http.Cookie;
        } else {
            if (client.ip ~ crawler_acl || req.http.User-Agent ~ "^(?:{{crawler_user_agent_regex}})$") {
                set req.http.Cookie = "frontend=no-session";
                set req.http.X-Varnish-Cookie = req.http.Cookie;
            } else {
                #pass so we can get a unique session
                return (pass);
            }
        }
        if ({{force_cache_static}} &&
                req.url ~ ".*\.(?:{{static_extensions}})(?=\?|&|$)") {
            unset req.http.Cookie;
            return (lookup);
        }
        if (req.url ~ "{{url_base_regex}}(?:{{url_excludes}})") {
            return (pipe);
        }
        if ({{enable_get_excludes}} &&
                req.url ~ "(?:[?&](?:{{get_param_excludes}})(?=[&=]|$))") {
            return (pass);
        }
        return (lookup);
    }
    # else it's not part of magento so do default handling (doesn't help
    # things underneath magento but we can't detect that)
}

sub vcl_pipe {
    # since we're not going to do any stuff to the response we pretend the
    # request didn't pass through Varnish
    unset req.http.X-Turpentine-Secret-Handshake;
    set req.http.Connection = "close";
    return (pipe);
}

sub vcl_pass {
    if (req.esi_level == 0 && req.http.X-Varnish-Cookie) {
        unset req.http.Cookie;
    } elsif (req.esi_level > 0 && req.http.X-Varnish-Cookie) {
        set req.http.Cookie = req.http.X-Varnish-Cookie;
    }
    return (pass);
}

sub vcl_hash {
    hash_data(req.url);
    if (req.http.Host) {
        hash_data(req.http.Host);
    } else {
        hash_data(server.ip);
    }
    if (req.http.X-Normalized-User-Agent) {
        hash_data(req.http.X-Normalized-User-Agent);
    }
    if (req.http.Accept-Encoding) {
        hash_data(req.http.Accept-Encoding);
    }
    if (req.url ~ "{{url_base_regex}}turpentine/esi/getBlock/") {
        if (req.url ~ "/{{esi_cache_type_param}}/private/"
                && req.http.Cookie ~ "frontend=") {
            hash_data(regsub(req.http.Cookie, "^.*?frontend=([^;]*);*.*$", "\1"));
            {{advanced_session_validation}}
        }
    }
    return (hash);
}

# This seems to cause cache object contention issues
# sub vcl_hit {
#     if (obj.hits > 0) {
#         set obj.ttl = obj.ttl + {{lru_factor}}s;
#     }
# }

sub vcl_miss {
    if (req.esi_level == 0 && req.http.X-Varnish-Cookie) {
        unset req.http.Cookie;
    } elsif (req.esi_level > 0 && req.http.X-Varnish-Cookie) {
        set req.http.Cookie = req.http.X-Varnish-Cookie;
    }
    return (fetch);
}

sub vcl_fetch {
    set req.grace = {{grace_period}}s;
    #TODO: only remove the User-Agent field from this if it exists
    unset beresp.http.Vary;

    if (beresp.status != 200 && beresp.status != 404) {
        set beresp.ttl = {{grace_period}}s;
        return (hit_for_pass);
    } else {
        if (beresp.http.Set-Cookie) {
            set beresp.http.X-Varnish-Set-Cookie = beresp.http.Set-Cookie;
            unset beresp.http.Set-Cookie;
        }
        if (req.esi_level == 0 &&
                req.http.X-Varnish-Cookie !~ "frontend=" &&
                client.ip !~ crawler_acl &&
                req.http.User-Agent !~ "^(?:{{crawler_user_agent_regex}})$") {
            set beresp.http.X-Varnish-Use-Set-Cookie = "1";
        }
        if (beresp.http.X-Turpentine-Esi == "1") {
            set beresp.do_esi = true;
        }
        set beresp.do_gzip = true;
        if (beresp.http.X-Turpentine-Cache == "0") {
            call remove_cache_headers;
            set beresp.ttl = {{grace_period}}s;
            return (hit_for_pass);
        } else {
            if ({{force_cache_static}} &&
                    bereq.url ~ ".*\.(?:{{static_extensions}})(?=\?|&|$)") {
                call remove_cache_headers;
                set beresp.ttl = {{static_ttl}}s;
                set beresp.http.Cache-Control = "max-age={{static_ttl}}";
            } elseif (req.url ~ "{{url_base_regex}}turpentine/esi/getBlock/") {
                call remove_cache_headers;
                if (req.url ~ "/{{esi_cache_type_param}}/private/" &&
                        req.http.Cookie ~ "frontend=") {
                    set beresp.http.X-Varnish-Session = regsub(req.http.Cookie,
                        "^.*?frontend=([^;]*);*.*$", "\1");
                }
                set beresp.ttl = std.duration(regsub(req.url,
                    ".*/{{esi_ttl_param}}/([0-9]+)/.*", "\1s"), 300s);
                if (beresp.ttl == 0s) {
                    set beresp.ttl = {{grace_period}}s;
                    return (hit_for_pass);
                }
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
    #GCC should optimize this entire branch away if debug headers are disabled
    if ({{debug_headers}}) {
        set resp.http.X-Varnish-Hits = obj.hits;
    } else {
        #remove Varnish fingerprints
        unset resp.http.X-Varnish;
        unset resp.http.Via;
        unset resp.http.X-Powered-By;
        unset resp.http.Server;
        unset resp.http.Age;
        unset resp.http.X-Turpentine-Cache;
        unset resp.http.X-Turpentine-Esi;
        unset resp.http.X-Varnish-Session;
        unset resp.http.X-Varnish-Set-Cookie;
        unset resp.http.X-Varnish-Use-Set-Cookie;
    }
}
