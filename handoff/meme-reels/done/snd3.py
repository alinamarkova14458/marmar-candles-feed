import numpy as np, json, wave, sys
d=sys.argv[1]; t=json.load(open(d+'/ev.json')); sr=44100; n=int(t['dur']*sr); a=np.zeros((n,2))
SFX='/tmp/claude-0/-home-user-marmar-candles-feed/3e2e06cc-da17-5318-82d1-83c362dc3422/scratchpad/sfx/'
def load(i):
    w=wave.open(SFX+f'{i}.wav'); x=np.frombuffer(w.readframes(w.getnframes()),np.int16).reshape(-1,2)/32768; return x
TYP,RECV,SENT,BELL=load(1393),load(2354),load(3005),load(933)
rng=np.random.default_rng(3)
def add(at,s,v=1.0):
    i=int(at*sr)
    if i>=n: return
    m=min(len(s),n-i); a[i:i+m]+=s[:m]*v
def typing(t0,t1,v=0.9):
    L=int((t1-t0)*sr); st=rng.integers(int(0.3*sr), len(TYP)-L-1); seg=TYP[st:st+L].copy()
    f=min(int(0.03*sr),L//4); seg[:f]*=np.linspace(0,1,f)[:,None]; seg[-f:]*=np.linspace(1,0,f)[:,None]; add(t0,seg,v)
kind=json.load(open(d+'.json'))['kind']
for at,s in t['ev']:
    if s=='l':
        if kind=='chat': typing(at-0.72,at-0.04)
        add(at,RECV,0.8)
    else:
        typing(at-0.5,at-0.06); add(at,SENT,0.9)
add(t['end'],BELL[:int(1.6*sr)]*np.linspace(1,0,int(1.6*sr))[:,None],0.6)
pk=np.abs(a).max()
if pk>0.98: a*=0.98/pk
w=wave.open(d+'/snd3.wav','wb'); w.setnchannels(2); w.setsampwidth(2); w.setframerate(sr); w.writeframes((a*32000).astype(np.int16).tobytes()); w.close()
