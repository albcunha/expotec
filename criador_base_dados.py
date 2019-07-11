import zlib # para descompactar o arquivo zip
import sys # soluções de encoding utf8 do texto
import sqlite3 #abrir arquivo como csv e salvar como base de dados sqlite3
import supersqlite # para a extensão fts3. não tive tempo de converter tudo em uma biblioteca só.
import timeit #informa o tempo de execução da pesquisa
import os
import subprocess
import requests
from bs4 import BeautifulSoup  # processa dados
from tqdm import tqdm  # barra de progresso
import pandas as pd###########################################################################################
import numpy as np
import time
import datetime
try:
    import winsound # aviso sonoro no final da execução do script
except:
    pass

# Criado por Alberto Cartaxo.
# é necessario atualizar o arquivo sqlite3.dll para um que permite fts3. basta copiar o arquivo correto para a pasta do python. ex: C:\anaconda3\DLLs

###########################################################################################

# download do site pelo modulo homura
def attributos_download(arquivo,termo):
    """ obtem dados dos download"""
    c=0
    for item in resultado:
        item = str(item).lower()
        if arquivo.lower() in item:
            c = 1
        if termo.lower() in item and c == 1:
            return item[item.find(':')+2:-5].strip()
def resource_path(relative):
    """ para o caso de ser criardo um executável binário """
    if hasattr(sys, "_MEIPASS"):
        return os.path.join(sys._MEIPASS, relative)
    return os.path.join(relative)
def download_dados(arquivo, tamanho):
    """ Faz download dos arquivos"""
    print('###########################################')
    print('Fazendo download: ',arquivo)
    print('###########################################')
    filename = "aria2c.exe"
    filename_opcoes = " --file-allocation=none --conditional-get=true --lowest-speed-limit=4k  --max-tries=20 "
    execucao = filename +  filename_opcoes + arquivo
    file = arquivo[arquivo.rfind('/')+1:]
    try: 
        statinfo = os.stat(file).st_size
    except: 
        statinfo = 0
    tentativa = 0
    while statinfo < tamanho and tentativa < 21:
        tentativa += 1
        if tentativa == 21:
            quit()
        if statinfo ==  tamanho:
            print('Download OK! :', arquivo,'\n\n')
            break
        if statinfo != 0:
            print('Reiniciando download. Tentativa:',str(tentativa) + '/20', datetime.datetime.now().time().strftime('%H:%M:%S'))
        time.sleep(5)
        download = os.system(resource_path(execucao))
        try: 
            statinfo = os.stat(file).st_size
        except: 
            statinfo = 0
 

def descompacta(arquivo):
    if arquivo.find('/') != -1:
        arquivo = arquivo[arquivo.rfind('/')+1:]
    start_time = timeit.default_timer()
    CHUNKSIZE=1024
    d = zlib.decompressobj(16+zlib.MAX_WBITS)
    fm = open(arquivo, 'rb')            
    fm_out = open(arquivo[:-3],'wb')
    print ('\n\nIniciando descompressão', arquivo, '.....')
    buffer=fm.read(CHUNKSIZE)
    while buffer:
        outstr = d.decompress(buffer)
        fm_out.write(outstr)
        buffer=fm.read(CHUNKSIZE)

    outstr = d.flush()
    fm.close()
    fm_out.close()
    print ('\n\nIniciando descompressão', arquivo, '.....ok!')
    print("--- %s minutos ---" % "{0:.3f}".format(float((timeit.default_timer() - start_time))/60))    
    start_time = timeit.default_timer()
    return None

def converte_mes_ano(x):
    x = str(x)
    return x[:-4].zfill(2) + '/' + x[-4:]

def reduz_cpf(x):
    return '###' + str(x)[3:-2] + '##'

def dinheiro(x):
    return 'R$ ' + '{:,.2f}'.format(x).replace('.', '%temp%').replace(',', '.').replace('%temp%', ',')

    
print ("________________________________________________________________________________")
print ("CRIADOR DE BASE DE DADOS PARA CARGOS TCE-PB. 23.06.2017")
print ("Aperte CTRL+C para cancelar o programa, ou feche a janela")
print ("________________________________________________________________________________\n")


r = requests.get('http://dados.tce.pb.gov.br/')

texto = r.text
soup = BeautifulSoup(texto, "html.parser")
resultado = soup.find_all('li')

# Para fazer o download automatico das colunas. A implementar
# Na tabela de atributos dos arquivos folha de pessoal municipal do tce, falta inserir a coluna dt_ano, que consta no arquivo do dump da base deles.
lista = str(resultado)

for item in resultado:
    try: #try foi um conserto rapido em razão de mudanças no site do dados.tce.pb.gov.
        if '<li>Arquivo:' in item:
            print(item)
    except: pass
dict_arquivos = {}
for c,item in enumerate(resultado):
    item = str(item)
    if '<li>Arquivo: ' in item:
        item = item[item.find(': ')+2:-5]
        dict_arquivos[item] = []

lista_arquivos = list(dict_arquivos.keys())
c = 0
for item in resultado:
    if c == len(lista_arquivos): #dá erro quanto aos arquivos GeoPB, mas como não me interessa, retirei da lista.
            break
    item = str(item)
    if '<li>  ' in item or '<li>\n' in item:
        dict_arquivos[lista_arquivos[c]].append(item[4:-6].strip())
    if '<li>Arquivo:' in item:
        c +=1

lista_arquivos = [ 'TCE-PB-SAGRES-Folha_Pessoal_Esfera_Municipal.txt.gz', 'TCE-PB-SAGRES-Folha_Pessoal_Esfera_Estadual.txt.gz' ]
for item in lista_arquivos:
    print('/////////' * 10)
    print('Arquivo considerado:',item)
    print('Colunas consideradas:')
    for subitem in dict_arquivos[item]:
        print(subitem)
    print('\n\n')
colunas = []

c = 0

cargos_data_geracao = attributos_download('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Municipal.txt', 'Data de Geração: ')
cargos_tamanho = attributos_download('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Municipal.txt', 'Tamanho: ')
cargos_hash_md5 = attributos_download('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Municipal.txt', 'MD5:')
municipio_tot_registros = attributos_download('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Municipal.txt', 'Quantidade de Registros:')
estado_tot_registros = attributos_download('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Estadual.txt', 'Quantidade de Registros:')
cargos_tot_registros = int(municipio_tot_registros) + int(estado_tot_registros)
tamanho_arquivo_municipal = attributos_download('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Municipal.txt', 'Tamanho:')
tamanho_arquivo_municipal = int(tamanho_arquivo_municipal[:tamanho_arquivo_municipal.find('bytes')])
tamanho_arquivo_estadual = attributos_download('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Estadual.txt', 'Tamanho:')
tamanho_arquivo_estadual = int(tamanho_arquivo_estadual[:tamanho_arquivo_estadual.find('bytes')])
start_time = timeit.default_timer()



#download dos arquivos
arquivo_municipal = "http://dados.tce.pb.gov.br/TCE-PB-SAGRES-Folha_Pessoal_Esfera_Municipal.txt.gz"
arquivo_estadual = 'http://dados.tce.pb.gov.br/TCE-PB-SAGRES-Folha_Pessoal_Esfera_Estadual.txt.gz'

download_dados(arquivo_municipal, tamanho_arquivo_municipal)
download_dados(arquivo_estadual, tamanho_arquivo_estadual)

print('Downloads encerrados.')

# decompacta arquivos
descompacta(arquivo_municipal)
descompacta(arquivo_estadual)

start_time = timeit.default_timer()
try: os.remove('Folhapessoal.db')
except: pass

conn = sqlite3.connect("Folhapessoal.db")
curs = conn.cursor()

print('\n\nCriando base de dados (arquivo Folhapessoal.db).... \n\n')
print('Inserindo Folha dos Municípios na base de dados....')
start_time = timeit.default_timer()
curs.executescript("""
PRAGMA synchronize = OFF;
PRAGMA journal_mode = MEMORY;
CREATE TABLE dados_download (
  ID INT PRIMARY KEY, data_geracao TEXT, tamanho TEXT , hash_md5 TEXT, cargos_tot_registros INT);
""")
conn.commit()
curs.execute("""INSERT INTO dados_download (\
  data_geracao, tamanho , hash_md5, cargos_tot_registros ) VALUES ( ?, ?, ?, ?);""",
             [cargos_data_geracao, cargos_tamanho, cargos_hash_md5, cargos_tot_registros])
conn.commit()

colunas = """cd_ugestora, de_ugestora, de_cargo, de_tipocargo, cd_CPF, dt_mesanoreferencia, dt_ano, no_servidor, vl_vantagens, de_uorcamentaria"""
colunas = colunas.split(',')
colunas = [x.strip() for x in colunas]
comando = ("""CREATE TABLE folhamunicipio (ID INT PRIMARY KEY ,""" +
           str(" TEXT,".join(colunas)) + " TEXT);")
curs.execute(comando)

f = open(arquivo_municipal[arquivo_municipal.rfind('/')+1:-3], 'r', encoding='utf8')
chunksize = 200000
i = 0
j = 1
dtype = {'cd_ugestora': np.int32,
           'de_ugestora': np.object, 
           'de_cargo': np.object,
           'de_tipocargo': np.object,
           'cd_CPF': np.object, 
           'dt_mesanoreferencia': np.object, 
           'dt_ano': np.int32,
           'no_servidor': np.int32, 
           'vl_vantagens': np.float32, 
           'de_uorcamentaria': np.object}
reader = pd.read_csv(f, sep='|', iterator = True, chunksize=chunksize, low_memory=False, 
                     dtype=dtype,  na_filter=False) 
for df in tqdm(reader, total=int(int(
                municipio_tot_registros) / chunksize), leave=True):
        df = df.rename(columns={c: c.replace(' ', '') for c in colunas}) 
        df['dt_MesAnoReferencia'] = df['dt_MesAnoReferencia'].apply(converte_mes_ano)
        df['cd_CPF'] = df['cd_CPF'].apply(reduz_cpf)
        df['vl_vantagens'] = df['vl_vantagens'].apply(dinheiro)
        df.index += j
        i+=1
        df.to_sql('folhamunicipio', conn, if_exists='append',index_label='ID')
        j = df.index[-1] + 1

conn.commit()
f.close()
print ('Inserindo Folha dos Municípios (Temp. aprox: 8 minutos) na base de dados....Ok!!!')
print("--- %s minutos ---" % "{0:.3f}".format(float((timeit.default_timer() - start_time))/60))

start_time = timeit.default_timer()


print ('Inserindo Folha do Estado(Temp. aprox: 8 minutos) na base de dados....')
colunas = """de_poder, de_orgaolotacao,  no_cargo, tp_cargo, nu_cpf, no_servidor, dt_mesano, dt_ano, dt_admissao, vl_vantagens"""

colunas = colunas.split(',')
colunas = [x.strip() for x in colunas]

comando = ("""CREATE TABLE folhaestado (ID INT PRIMARY KEY ,""" +
           str(" TEXT,".join(colunas)) + " TEXT);")
curs.execute(comando)

f = open(arquivo_estadual[arquivo_municipal.rfind('/')+1:-3], 'r', encoding='utf8')
chunksize = 500000
i = 0
j = 1
reader = pd.read_csv(f, sep='|', iterator = True, chunksize=chunksize, low_memory=False, 
                     dtype=dtype,  na_filter=False)
for df in tqdm(reader, total=int(int(
                estado_tot_registros) / chunksize), leave=True):
    
        df = df.rename(columns={c: c.replace(' ', '') for c in colunas}) 
        df['dt_mesano'] = df['dt_mesano'].apply(converte_mes_ano)   
        df['nu_cpf'] = df['nu_cpf'].apply(reduz_cpf)
        df['vl_vantagens'] = df['vl_vantagens'].apply(dinheiro)
        df.index += j
        i+=1
        df.to_sql('folhaestado', conn, if_exists='append',index_label='ID')
        j = df.index[-1] + 1

conn.commit()
f.close()
print ('Inserindo Folha do Estado(Temp. aprox: 8 minutos) na base de dados....Ok!!!')
print("--- %s minutos ---" % "{0:.3f}".format(float((timeit.default_timer() - start_time))/60))

start_time = timeit.default_timer()

print ('Criando tabela FTS3 Município (12 minutos)....')

os.remove('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Municipal.txt.gz')
os.remove('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Municipal.txt')
os.remove('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Estadual.txt.gz')
os.remove('TCE-PB-SAGRES-Folha_Pessoal_Esfera_Estadual.txt')

conn.close()

# cria modulo FTS3 para as tabelas
# usa superqlite por conta das extensões sqlite fts3

start_time = timeit.default_timer()
tmp_db = supersqlite.SuperSQLite.connect("Folhapessoal.db")
tmp_db.cursor().execute('CREATE VIRTUAL TABLE folhaparaiba USING fts3(servidor, cpf, cargo, natureza, mes_ano, remuneracao, poder, orgao, data_admissao tokenize=unicode61);')
# insere dados
print ('Criando tabela FTS3 Município (Temp. aprox: 12 minutos)....ok!')
tmp_db.cursor().execute('INSERT INTO folhaparaiba SELECT no_servidor, cd_cpf, de_cargo, de_tipocargo, dt_mesanoreferencia, vl_vantagens, de_ugestora, de_uorcamentaria, "" FROM folhamunicipio;')
print("--- %s minutos ---" % "{0:.3f}".format(float((timeit.default_timer() - start_time))/60))


#curs.execute('CREATE VIRTUAL TABLE folhaparaiba USING fts3(servidor, cpf, cargo, natureza, mes_ano, remuneracao, poder, orgao, data_admissao tokenize=unicode61);')


#curs.execute('INSERT INTO folhaparaiba SELECT no_servidor, cd_cpf, de_cargo, de_tipocargo, dt_mesanoreferencia, vl_vantagens, de_ugestora, de_uorcamentaria, "" FROM folhamunicipio;')

start_time = timeit.default_timer()
print ('Criando tabela FTS3 folhaparaiba (Temp. aprox: 12 minutos)....')

tmp_db.cursor().execute('INSERT INTO folhaparaiba SELECT no_servidor, nu_cpf, no_cargo, tp_cargo, dt_mesano, vl_vantagens, de_poder, de_orgaolotacao, dt_admissao FROM folhaestado;')

#conn.commit()
#conn.close()
print ('Criando tabela FTS3 folhaparaiba (Temp. aprox: 12 minutos)....ok!')
print("--- %s minutos ---" % "{0:.3f}".format(float((timeit.default_timer() - start_time))/60))

input('Encerrado! Pressione Enter para sair do programa')


            
            
