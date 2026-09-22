# Mixed clients: Windows CNA always opens /redirect (Microsoft). Phones
# follow a 302 to http://192.168.88.1/login. dns-name must be empty —
# a Microsoft dns-name makes Android/iPhone refuse the login jump.

/ip dns set allow-remote-requests=yes
/ip dns static remove [find comment="Kasi CNA"]
/ip dns static remove [find comment="Kasi NCSI DNS"]
/ip dns static
add name=dns.msftncsi.com address=131.107.255.255 ttl=1d comment="Kasi NCSI DNS"

/ip hotspot profile
set [find] dns-name="" html-directory=hotspot login-by=cookie,http-chap,http-pap

/file remove [find name="hotspot/error.html"]
/tool fetch url="http://192.168.88.10:8000/hotspot-login.html" dst-path=hotspot/login.html
/tool fetch url="http://192.168.88.10:8000/hotspot-login.html" dst-path=hotspot/redirect.html
/tool fetch url="http://192.168.88.10:8000/captive-portal.json" dst-path=hotspot/captive.json

/ip dhcp-server option remove [find name=kasi-capport]
/ip dhcp-server option add name=kasi-capport code=114 value="'http://192.168.88.1/captive.json'"
/ip dhcp-server network set [find] dhcp-option=kasi-capport dns-server=192.168.88.1

/ip firewall filter
remove [find comment="Kasi block DoH"]
add chain=forward protocol=udp dst-port=853 action=drop comment="Kasi block DoH"
add chain=forward protocol=tcp dst-port=853 action=drop comment="Kasi block DoH"

/ip firewall nat
remove [find comment="Kasi force DNS"]
add chain=dstnat in-interface=bridge protocol=udp dst-port=53 action=redirect to-ports=53 comment="Kasi force DNS"
add chain=dstnat in-interface=bridge protocol=tcp dst-port=53 action=redirect to-ports=53 comment="Kasi force DNS"

/ipv6 settings set disable-ipv6=yes
