/**
 * Monster Analytics, v.1.0
 */
var ma = {
    create: function(d,url){
		this.a='MA-0001';
		this.d=d;
		this.url=url;
		return true;
    },
	send: function(){
		var $this=this;
		if (window.XMLHttpRequest) { // Mozilla, Safari, ...
			httpRequest = new XMLHttpRequest();
		}else if(window.ActiveXObject) { // IE
			try {
				httpRequest = new ActiveXObject("Msxml2.XMLHTTP");
			} catch (e) {
				try {
					httpRequest = new ActiveXObject("Microsoft.XMLHTTP");
				} catch (e) {}
			}
		}
		if (!httpRequest) {
		  alert('Giving up :( Cannot create an XMLHTTP instance');
		  return false;
		}
		httpRequest.onreadystatechange = function(){
			if (httpRequest.readyState==4 && httpRequest.status==200){
				//alert(httpRequest.responseText); //return saved id on md5
				if(!$this.sr() && httpRequest.responseText!='failed')
					$this.sc('_ma',httpRequest.responseText);
			}
		}
		httpRequest.open('POST', this.url, true);
		httpRequest.setRequestHeader("Content-type","application/x-www-form-urlencoded");
		httpRequest.send(this.pd());
	},
	rf: function(){ //url referrer
		return this.d.referrer;
	},
	pt: function(){ //page title
		return this.d.getElementsByTagName('title').item(0).innerHTML;
	},
	pu: function(){
		return this.d.URL;
	},
	pd: function(){ //page data
		var ar = [
			'a='+this.a,
			't='+this.pt(),
			'r='+this.rf(),
			'p='+this.ptf(),
			'b='+this.ag(),
			's='+this.sr(),
			'u='+this.pu(),
		];
		return ar.join('&');
	},
	ptf: function(){//platform information
		var wn = window.navigator;
       	return wn.platform.toString().toLowerCase();
	},
	ag: function(){//user agent information
		var wn = window.navigator,
        userAgent = wn.userAgent.toLowerCase(),
        storedName;
		if (userAgent.indexOf('msie',0) !== -1) {
		    browserName = 'ie';
		    os = 'win';
		    storedName = userAgent.match(/msie[ ]\d{1}/).toString();
		    version = storedName.replace(/msie[ ]/,'');

		    browserOsVersion = browserName + version;
		}
		
		return userAgent;
	},
	sr: function(){//session
		var nameEQ = "_ma=";
        var ca = document.cookie.split(';');
        for(var i=0;i < ca.length;i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
        }
        return false;
	},
    sc: function(name,value){//session create
        var date = new Date();
        date.setTime(date.getTime()+(30*60*1000));
		var expdate = date.toGMTString();
        var expires = "; expires="+expdate;
        document.cookie = name+"="+value+'-'+expdate+expires+"; path=/";
    },
}
