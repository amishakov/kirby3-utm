(function(){"use strict";const $="";function d(s,t,o,a,i,_,f,C){var n=typeof s=="function"?s.options:s;t&&(n.render=t,n.staticRenderFns=o,n._compiled=!0),a&&(n.functional=!0),_&&(n._scopeId="data-v-"+_);var r;if(f?(r=function(e){e=e||this.$vnode&&this.$vnode.ssrContext||this.parent&&this.parent.$vnode&&this.parent.$vnode.ssrContext,!e&&typeof __VUE_SSR_CONTEXT__<"u"&&(e=__VUE_SSR_CONTEXT__),i&&i.call(this,e),e&&e._registeredComponents&&e._registeredComponents.add(f)},n._ssrRegister=r):i&&(r=C?function(){i.call(this,(n.functional?this.parent:this).$root.$options.shadowRoot)}:i),r)if(n.functional){n._injectStyles=r;var b=n.render;n.render=function(R,u){return r.call(u),b(R,u)}}else{var l=n.beforeCreate;n.beforeCreate=l?[].concat(l,r):[r]}return{exports:s,options:n}}const p={name:"Utmbar",props:{data:Array}};var v=function(){var t=this,o=t._self._c;return o("div",{staticClass:"utm-bar-wrapper"},t._l(t.data,function(a){return o("div",{class:a.theme,style:a.style,attrs:{title:a.date}},[o("div",{domProps:{innerHTML:t._s(a.amount)}})])}),0)},c=[],m=d(p,v,c,!1,null,null,null,null);const h=m.exports;panel.plugin("bnomei/utm",{fields:{utmbar:h}})})();
