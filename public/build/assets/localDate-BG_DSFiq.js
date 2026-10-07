function o(t=new Date){const e=t instanceof Date?t:new Date(t);return new Date(e.getTime()-e.getTimezoneOffset()*6e4).toISOString().slice(0,10)}export{o as l};
