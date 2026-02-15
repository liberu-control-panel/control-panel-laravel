{{/*
Expand the name of the chart.
*/}}
{{- define "control-panel.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
*/}}
{{- define "control-panel.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "control-panel.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "control-panel.labels" -}}
helm.sh/chart: {{ include "control-panel.chart" . }}
{{ include "control-panel.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "control-panel.selectorLabels" -}}
app.kubernetes.io/name: {{ include "control-panel.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "control-panel.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "control-panel.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
MySQL service name
*/}}
{{- define "control-panel.mysql.fullname" -}}
{{- printf "%s-mysql" (include "control-panel.fullname" .) | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Redis service name
*/}}
{{- define "control-panel.redis.fullname" -}}
{{- printf "%s-redis-master" (include "control-panel.fullname" .) | trunc 63 | trimSuffix "-" }}
{{- end }}
