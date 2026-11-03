import json,subprocess,os
jobs=json.load(open('jobs.json'))
D=2.2; X=0.4; FPS=30
for name,n in jobs:
    clips=[]
    for i in range(n):
        c=f'out/{name}-c{i}.mp4'
        fr=int(D*FPS)
        subprocess.run(['ffmpeg','-y','-loglevel','error','-loop','1','-i',f'frames/{name}-{i}.png','-vf',
          f"scale=2000:3000,zoompan=z='1+0.035*on/{fr}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d={fr}:s=1000x1500:fps={FPS},format=yuv420p",
          '-t',str(D),'-c:v','libx264','-preset','veryfast','-crf','20',c],check=True)
        clips.append(c)
    inp=[]; 
    for c in clips: inp+=['-i',c]
    fc=''; last='[0:v]'; off=D-X
    for i in range(1,n):
        out=f'[v{i}]'; fc+=f"{last}[{i}:v]xfade=transition=fade:duration={X}:offset={off:.2f}{out};"; last=out; off+=D-X
    subprocess.run(['ffmpeg','-y','-loglevel','error',*inp,'-filter_complex',fc.rstrip(';'),'-map',last,'-c:v','libx264','-preset','medium','-crf','21','-pix_fmt','yuv420p','-movflags','+faststart',f'out/{name}.mp4'],check=True)
    for c in clips: os.remove(c)
    print(name, os.path.getsize(f'out/{name}.mp4')//1024,'KB')
